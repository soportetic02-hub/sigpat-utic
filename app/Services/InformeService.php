<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Models\InformeEquipoModel;
use App\Models\InformeEvidenciaModel;
use App\Models\InformeModel;
use App\Models\ParametroModel;
use App\Models\UsuarioSistemaModel;
use DateTimeImmutable;
use Throwable;

/**
 * Informes técnicos de evaluación, inoperatividad y baja (sección 7.4).
 *
 * BORRADOR: editable (datos, equipos y evidencias).
 * EMITIDO: inmutable, numerado N° 001-AAAA/OTIC; si la acción es BAJA_DEFINITIVA
 * los equipos quedan con recomendado_baja = 1 (la baja la confirma un ADMINISTRADOR).
 */
final class InformeService
{
    /** Frases frecuentes para el diagnóstico (el técnico puede editarlas o escribir texto libre). */
    public const DIAGNOSTICOS_RAPIDOS = [
        'Falla de encendido: el equipo no enciende ni muestra actividad.',
        'Fatiga térmica: sobrecalentamiento recurrente que provoca apagados.',
        'Obsolescencia tecnológica: el hardware no soporta el software institucional vigente.',
        'Daño en la placa madre (tarjeta principal).',
        'Fuente de poder averiada.',
        'Disco duro con sectores dañados o sin lectura.',
        'Memoria RAM defectuosa.',
        'Pantalla o panel de visualización dañado.',
        'Batería agotada o hinchada.',
        'Cabezal de impresión obstruido o dañado.',
        'Mecanismo de alimentación de papel averiado.',
        'Repuestos descontinuados en el mercado.',
        'El costo de reparación supera el valor actual del equipo.',
    ];

    private InformeModel $informes;
    private InformeEquipoModel $equiposInforme;
    private InformeEvidenciaModel $evidencias;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->informes = new InformeModel();
        $this->equiposInforme = new InformeEquipoModel();
        $this->evidencias = new InformeEvidenciaModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /**
     * @return array{informe: array<string, mixed>, equipos: list<array<string, mixed>>, evidencias: list<array<string, mixed>>}
     */
    public function obtenerCompleto(int $id): array
    {
        $informe = $this->informes->detalle($id);
        if ($informe === null) {
            throw new HttpException(404, 'El informe no existe.');
        }

        return [
            'informe'    => $informe,
            'equipos'    => $this->equiposInforme->porInforme($id),
            'evidencias' => $this->evidencias->porInforme($id),
        ];
    }

    /**
     * Valores iniciales de un informe nuevo: Para = jefe de la OTIC, De = usuario en sesión.
     *
     * @return array<string, string>
     */
    public function valoresPorDefecto(): array
    {
        $parametros = new ParametroModel();
        $usuario = Auth::user() ?? [];

        return [
            'para_nombre' => (string) $parametros->obtener('jefe_otic_nombre', ''),
            'para_cargo'  => (string) $parametros->obtener('jefe_otic_cargo', 'Jefe de la Oficina de Tecnologías de la Información y Comunicación'),
            'de_nombre'   => trim(($usuario['nombres'] ?? '') . ' ' . ($usuario['apellidos'] ?? '')),
            'de_cargo'    => (string) ($usuario['cargo'] ?? 'Técnico de la OTIC'),
            'fecha'       => date('Y-m-d'),
        ];
    }

    /** @param array<string, mixed> $entrada */
    public function crear(array $entrada, int $usuarioId): int
    {
        ['datos' => $datos, 'equipos' => $filas] = $this->validar($entrada, null);
        $autor = (new UsuarioSistemaModel())->find($usuarioId);
        if ($autor === null) {
            throw new HttpException(403);
        }

        $this->db->beginTransaction();
        try {
            $id = $this->informes->insert($datos + [
                'de_usuario_id' => $usuarioId,
                'de_nombre'     => trim($autor['nombres'] . ' ' . $autor['apellidos']),
                'de_cargo'      => $autor['cargo'] ?? 'Técnico de la OTIC',
                'estado'        => 'BORRADOR',
                'created_by'    => $usuarioId,
            ]);
            $this->equiposInforme->reemplazar($id, $filas);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'informes_tecnicos', $id, null, $this->instantanea($id), $usuarioId);
            $this->db->commit();

            return $id;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $entrada */
    public function actualizar(int $id, array $entrada, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $informe = $this->borradorBloqueado($id);
            $actuales = array_map(static fn (array $f): int => (int) $f['equipo_id'], $this->equiposInforme->porInforme($id));
            ['datos' => $datos, 'equipos' => $filas] = $this->validar($entrada, $actuales);
            $antes = $this->instantanea($id);

            $this->informes->update($id, $datos);
            $this->equiposInforme->reemplazar($id, $filas);
            $this->evidencias->desasociarEquiposAusentes($id);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'informes_tecnicos', (int) $informe['id'], $antes, $this->instantanea($id), $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Emite el informe: correlativo anual, estado EMITIDO (inmutable) y, si la acción
     * es BAJA_DEFINITIVA, recomendado_baja = 1 en sus equipos. Todo en una transacción.
     * Después genera el PDF y el Word.
     */
    public function emitir(int $id, int $usuarioId): string
    {
        $this->db->beginTransaction();
        try {
            $informe = $this->borradorBloqueado($id);
            $equipos = $this->equiposInforme->porInforme($id);

            $errores = [];
            if ($equipos === []) {
                $errores['equipos'] = 'Agregue al menos un equipo evaluado.';
            }
            foreach ($equipos as $eq) {
                if (trim((string) $eq['diagnostico']) === '') {
                    $errores['equipos'] = sprintf('Falta el diagnóstico técnico de %s %s %s.', $eq['tipo'], $eq['marca'], $eq['modelo']);
                    break;
                }
            }
            if (trim((string) $informe['conclusiones']) === '') {
                $errores['conclusiones'] = 'Redacte las conclusiones antes de emitir.';
            }
            if (trim((string) $informe['para_nombre']) === '') {
                $errores['para_nombre'] = 'Indique el destinatario (Para).';
            }
            if ($errores !== []) {
                throw new ValidationException($errores, 'El informe no puede emitirse todavía: ' . implode(' ', $errores));
            }

            $numero = (new CorrelativoService())->siguiente(CorrelativoService::INFORME);
            $this->informes->update($id, [
                'numero'      => $numero['numero'],
                'anio'        => $numero['anio'],
                'correlativo' => $numero['correlativo'],
                'estado'      => 'EMITIDO',
                'emitido_por' => $usuarioId,
                'emitido_at'  => date('Y-m-d H:i:s'),
            ]);

            $marcados = 0;
            if ($informe['accion_requerida'] === 'BAJA_DEFINITIVA') {
                $marcados = $this->informes->marcarRecomendadoBaja($id, $usuarioId);
            }

            $this->auditoria->registrar(
                AuditoriaService::EMITIR,
                'informes_tecnicos',
                $id,
                ['estado' => 'BORRADOR'],
                [
                    'estado'           => 'EMITIDO',
                    'numero'           => $numero['numero'],
                    'accion_requerida' => $informe['accion_requerida'],
                    'equipos'          => array_map(static fn (array $e): int => (int) $e['equipo_id'], $equipos),
                    'recomendados_baja' => $marcados,
                ],
                $usuarioId
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->generarDocumentosSeguro($id);

        return $numero['numero'];
    }

    /** Elimina un borrador con sus equipos y evidencias (archivos incluidos). */
    public function anular(int $id, int $usuarioId): void
    {
        $archivos = [];
        $this->db->beginTransaction();
        try {
            $informe = $this->borradorBloqueado($id);
            if ((int) $informe['created_by'] !== $usuarioId && !Auth::esAdministrador()) {
                throw new HttpException(403, 'Solo el autor del borrador o un administrador puede anularlo.');
            }
            $archivos = array_column($this->evidencias->porInforme($id), 'archivo');
            $antes = $this->instantanea($id);
            $this->informes->eliminarBorrador($id);
            $this->auditoria->registrar(AuditoriaService::ELIMINAR, 'informes_tecnicos', $id, $antes, null, $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $servicio = new EvidenciaService();
        foreach ($archivos as $archivo) {
            $servicio->eliminarArchivo((string) $archivo);
        }
    }

    /**
     * Sube varias fotos al borrador. $descripciones y $equiposFoto van indexados
     * igual que los archivos. Devuelve cuántas se guardaron.
     *
     * @param list<array<string, mixed>> $archivos normalizados con EvidenciaService::normalizarMultiples()
     * @param array<int|string, mixed> $descripciones
     * @param array<int|string, mixed> $equiposFoto
     */
    public function subirEvidencias(int $id, array $archivos, array $descripciones, array $equiposFoto, int $usuarioId): int
    {
        if ($archivos === []) {
            throw ValidationException::campo('evidencias', 'Seleccione al menos una foto.');
        }
        if (count($archivos) > EvidenciaService::MAX_POR_ENVIO) {
            throw ValidationException::campo('evidencias', sprintf('Puede subir como máximo %d fotos por envío.', EvidenciaService::MAX_POR_ENVIO));
        }

        $servicio = new EvidenciaService();
        $guardados = [];
        $this->db->beginTransaction();
        try {
            $this->borradorBloqueado($id);
            if ($this->evidencias->contar($id) + count($archivos) > EvidenciaService::MAX_POR_INFORME) {
                throw ValidationException::campo('evidencias', sprintf('Un informe admite como máximo %d fotos.', EvidenciaService::MAX_POR_INFORME));
            }
            $equiposValidos = array_map(static fn (array $f): int => (int) $f['equipo_id'], $this->equiposInforme->porInforme($id));
            $orden = $this->evidencias->siguienteOrden($id);

            foreach ($archivos as $archivo) {
                $i = $archivo['indice'] ?? 0;
                $descripcion = is_scalar($descripciones[$i] ?? null) ? trim((string) $descripciones[$i]) : '';
                if (mb_strlen($descripcion) > 255) {
                    throw ValidationException::campo('evidencias', 'La descripción de cada foto admite como máximo 255 caracteres.');
                }
                $equipoFoto = is_scalar($equiposFoto[$i] ?? null) ? (int) $equiposFoto[$i] : 0;
                $equipoFoto = in_array($equipoFoto, $equiposValidos, true) ? $equipoFoto : null;

                $datos = $servicio->guardar($archivo);
                $guardados[] = $datos['archivo'];
                $evidenciaId = $this->evidencias->insert($datos + [
                    'informe_id'  => $id,
                    'equipo_id'   => $equipoFoto,
                    'descripcion' => $descripcion === '' ? null : $descripcion,
                    'orden'       => $orden++,
                    'subido_por'  => $usuarioId,
                ]);
                $this->auditoria->registrar(AuditoriaService::CREAR, 'informe_evidencias', $evidenciaId, null,
                    ['informe_id' => $id, 'archivo' => $datos['archivo'], 'nombre_original' => $datos['nombre_original'], 'descripcion' => $descripcion], $usuarioId);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            foreach ($guardados as $archivo) {
                $servicio->eliminarArchivo($archivo);
            }
            throw $e;
        }

        return count($guardados);
    }

    public function eliminarEvidencia(int $id, int $evidenciaId, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $this->borradorBloqueado($id);
            $evidencia = $this->evidencias->find($evidenciaId);
            if ($evidencia === null || (int) $evidencia['informe_id'] !== $id) {
                throw ValidationException::campo('evidencias', 'La foto no pertenece a este informe.');
            }
            $this->evidencias->eliminar($evidenciaId);
            $this->auditoria->registrar(AuditoriaService::ELIMINAR, 'informe_evidencias', $evidenciaId, $evidencia, null, $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        (new EvidenciaService())->eliminarArchivo((string) $evidencia['archivo']);
    }

    /**
     * PDF del informe. Emitido: el archivo guardado (se regenera si falta).
     * Borrador: vista previa en memoria, con marca "BORRADOR" y sin número.
     *
     * @return array{contenido: ?string, ruta: ?string, nombre: string}
     */
    public function pdf(int $id): array
    {
        $datos = $this->obtenerCompleto($id);
        $informe = $datos['informe'];
        $pdf = new PdfService();

        if ($informe['estado'] !== 'EMITIDO') {
            return [
                'contenido' => $pdf->generar('informe_tecnico', $this->datosDocumento($datos) + ['borrador' => true], 'Informe técnico (borrador)'),
                'ruta'      => null,
                'nombre'    => 'Informe_borrador_' . $id . '.pdf',
            ];
        }

        $ruta = $pdf->rutaAbsoluta($informe['pdf_ruta']);
        if ($ruta === null) {
            $this->generarPdf($id);
            $ruta = $pdf->rutaAbsoluta((string) $this->informes->find($id)['pdf_ruta']);
        }
        if ($ruta === null) {
            throw new HttpException(500, 'No se pudo generar el PDF del informe.');
        }

        return ['contenido' => null, 'ruta' => $ruta, 'nombre' => 'Informe_Tecnico_' . str_replace('/', '-', (string) $informe['numero']) . '.pdf'];
    }

    /**
     * Word (.docx) de un informe EMITIDO (se regenera si falta).
     *
     * @return array{ruta: string, nombre: string}
     */
    public function word(int $id): array
    {
        $informe = $this->informes->find($id);
        if ($informe === null) {
            throw new HttpException(404, 'El informe no existe.');
        }
        if ($informe['estado'] !== 'EMITIDO') {
            throw ValidationException::campo('word', 'El documento Word está disponible cuando el informe se emite.');
        }

        $pdf = new PdfService();
        $ruta = $pdf->rutaAbsoluta($informe['docx_ruta']);
        if ($ruta === null) {
            $this->generarWord($id);
            $ruta = $pdf->rutaAbsoluta((string) $this->informes->find($id)['docx_ruta']);
        }
        if ($ruta === null) {
            throw new HttpException(500, 'No se pudo generar el documento Word.');
        }

        return ['ruta' => $ruta, 'nombre' => 'Informe_Tecnico_' . str_replace('/', '-', (string) $informe['numero']) . '.docx'];
    }

    public function generarPdf(int $id): void
    {
        $datos = $this->obtenerCompleto($id);
        $pdf = new PdfService();
        $contenido = $pdf->generar('informe_tecnico', $this->datosDocumento($datos) + ['borrador' => false], 'Informe técnico N° ' . $datos['informe']['numero']);
        $relativa = $pdf->guardar($contenido, 'informes', $this->nombreArchivo($datos['informe'], 'pdf'));
        $this->informes->update($id, ['pdf_ruta' => $relativa]);
    }

    public function generarWord(int $id): void
    {
        $datos = $this->obtenerCompleto($id);
        $relativa = (new WordService())->generarInforme($this->datosDocumento($datos), 'informes', $this->nombreArchivo($datos['informe'], 'docx'));
        $this->informes->update($id, ['docx_ruta' => $relativa]);
    }

    /**
     * Datos comunes para el PDF y el Word (fotos con su ruta absoluta).
     *
     * @param array{informe: array<string, mixed>, equipos: list<array<string, mixed>>, evidencias: list<array<string, mixed>>} $datos
     * @return array<string, mixed>
     */
    private function datosDocumento(array $datos): array
    {
        $servicio = new EvidenciaService();
        $fotos = [];
        foreach ($datos['evidencias'] as $ev) {
            $ruta = $servicio->ruta((string) $ev['archivo']);
            if ($ruta !== null) {
                $fotos[] = $ev + ['ruta' => $ruta];
            }
        }

        return [
            'informe'    => $datos['informe'],
            'equipos'    => $datos['equipos'],
            'evidencias' => $fotos,
        ];
    }

    private function generarDocumentosSeguro(int $id): void
    {
        try {
            $this->generarPdf($id);
        } catch (Throwable $e) {
            Logger::exception($e, 'No se pudo generar el PDF del informe #' . $id);
        }
        try {
            $this->generarWord($id);
        } catch (Throwable $e) {
            Logger::exception($e, 'No se pudo generar el Word del informe #' . $id);
        }
    }

    /** @param array<string, mixed> $informe */
    private function nombreArchivo(array $informe, string $extension): string
    {
        return 'informe_' . $informe['id'] . '_' . str_replace('/', '-', (string) $informe['numero']) . '.' . $extension;
    }

    /** Resumen de hardware para prellenar "Características". */
    /** @param array<string, mixed> $e */
    public static function caracteristicasSugeridas(array $e): string
    {
        $partes = [];
        foreach (['procesador' => '%s', 'ram_gb' => '%s GB de RAM', 'sistema_operativo' => '%s'] as $campo => $formato) {
            if (($e[$campo] ?? null) !== null && $e[$campo] !== '') {
                $partes[] = sprintf($formato, $e[$campo]);
            }
        }
        if (($e['disco_capacidad_gb'] ?? null) !== null) {
            $partes[] = trim(($e['disco_tipo'] ?? '') . ' de ' . $e['disco_capacidad_gb'] . ' GB');
        }
        if (($e['fecha_adquisicion'] ?? null) !== null) {
            $partes[] = 'adquirido en ' . date('Y', (int) strtotime((string) $e['fecha_adquisicion']));
        }
        $texto = implode(', ', $partes);

        return $texto === '' ? '' : mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1) . '.';
    }

    /** @return array<string, mixed> */
    private function borradorBloqueado(int $id): array
    {
        $informe = $this->informes->findForUpdate($id);
        if ($informe === null) {
            throw new HttpException(404, 'El informe no existe.');
        }
        if ($informe['estado'] !== 'BORRADOR') {
            throw ValidationException::campo('estado', 'El informe ya fue emitido y no puede modificarse.');
        }

        return $informe;
    }

    /**
     * @param array<string, mixed> $e
     * @param list<int>|null $equiposActuales equipos ya incluidos (se permiten aunque hoy estén de baja)
     * @return array{datos: array<string, mixed>, equipos: list<array{equipo_id: int, caracteristicas: ?string, estado_funcional: ?string, diagnostico: ?string}>}
     */
    private function validar(array $e, ?array $equiposActuales): array
    {
        $txt = static fn (string $c): string => is_scalar($e[$c] ?? null) ? trim((string) $e[$c]) : '';
        $nulo = static fn (string $c): ?string => $txt($c) === '' ? null : $txt($c);
        $errores = [];

        $datos = [
            'para_nombre'      => $txt('para_nombre'),
            'para_cargo'       => $txt('para_cargo'),
            'asunto'           => $txt('asunto'),
            'fecha'            => $txt('fecha'),
            'antecedentes'     => $nulo('antecedentes'),
            'accion_requerida' => $txt('accion_requerida'),
            'conclusiones'     => $nulo('conclusiones'),
            'recomendaciones'  => $nulo('recomendaciones'),
        ];

        if ($datos['para_nombre'] === '' || mb_strlen($datos['para_nombre']) > 150) {
            $errores['para_nombre'] = 'Indique el destinatario (máximo 150 caracteres).';
        }
        if ($datos['para_cargo'] === '' || mb_strlen($datos['para_cargo']) > 150) {
            $errores['para_cargo'] = 'Indique el cargo del destinatario (máximo 150 caracteres).';
        }
        if ($datos['asunto'] === '' || mb_strlen($datos['asunto']) > 255) {
            $errores['asunto'] = 'Indique el asunto (máximo 255 caracteres).';
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $datos['fecha']);
        if ($fecha === false || $fecha->format('Y-m-d') !== $datos['fecha'] || (int) $fecha->format('Y') < 2000) {
            $errores['fecha'] = 'Ingrese una fecha válida.';
        } elseif ($fecha > new DateTimeImmutable('today')) {
            $errores['fecha'] = 'La fecha del informe no puede ser futura.';
        }
        if (!in_array($datos['accion_requerida'], InformeModel::ACCIONES, true)) {
            $errores['accion_requerida'] = 'Seleccione la acción requerida.';
        }
        foreach (['antecedentes', 'conclusiones', 'recomendaciones'] as $campo) {
            if ($datos[$campo] !== null && mb_strlen($datos[$campo]) > 5000) {
                $errores[$campo] = 'Máximo 5000 caracteres.';
            }
        }

        // Equipos evaluados
        $ids = is_array($e['equipos'] ?? null) ? $e['equipos'] : [];
        $ids = array_values(array_unique(array_filter(array_map('intval', array_filter($ids, 'is_scalar')), static fn (int $i): bool => $i > 0)));
        $filas = [];
        if ($ids === []) {
            $errores['equipos'] = 'Agregue al menos un equipo evaluado.';
        } elseif (count($ids) > 50) {
            $errores['equipos'] = 'Un informe admite como máximo 50 equipos.';
        } else {
            $existentes = [];
            foreach ((new InformeEquipoModel())->equiposPorIds($ids) as $fila) {
                $existentes[(int) $fila['id']] = $fila;
            }
            $campo = static fn (string $c, int $id): ?string => is_array($e[$c] ?? null) && is_scalar($e[$c][$id] ?? null) && trim((string) $e[$c][$id]) !== ''
                ? trim((string) $e[$c][$id]) : null;
            foreach ($ids as $id) {
                $eq = $existentes[$id] ?? null;
                if ($eq === null) {
                    $errores['equipos'] = 'Uno de los equipos seleccionados no existe.';
                    continue;
                }
                if ($eq['estado_operativo'] === 'DE_BAJA' && !in_array($id, $equiposActuales ?? [], true)) {
                    $errores['equipos'] = sprintf('El equipo %s %s ya está dado de baja.', $eq['marca'], $eq['modelo']);
                    continue;
                }
                $fila = [
                    'equipo_id'        => $id,
                    'caracteristicas'  => $campo('caracteristicas', $id),
                    'estado_funcional' => $campo('estado_funcional', $id),
                    'diagnostico'      => $campo('diagnostico', $id),
                ];
                foreach (['caracteristicas', 'estado_funcional', 'diagnostico'] as $c) {
                    if ($fila[$c] !== null && mb_strlen($fila[$c]) > 3000) {
                        $errores['equipos'] = 'Cada campo de evaluación admite como máximo 3000 caracteres.';
                    }
                }
                $filas[] = $fila;
            }
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return ['datos' => $datos, 'equipos' => $filas];
    }

    /** @return array<string, mixed> */
    private function instantanea(int $id): array
    {
        $informe = $this->informes->find($id) ?? [];
        $informe['equipos'] = array_map(
            static fn (array $f): array => ['equipo_id' => (int) $f['equipo_id'], 'diagnostico' => $f['diagnostico']],
            $this->equiposInforme->porInforme($id)
        );

        return $informe;
    }
}
