<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\ValidationException;
use App\Models\CatalogoSoftwareModel;
use App\Models\ChecklistItemModel;
use App\Models\EquipoModel;
use App\Models\LicenciaEquipoModel;
use App\Models\MantenimientoDetalleModel;
use App\Models\MantenimientoEnvioModel;
use App\Models\MantenimientoModel;
use App\Models\ParametroModel;
use App\Models\UsuarioSistemaModel;
use DateTimeImmutable;
use Throwable;

/**
 * Actas de mantenimiento (sección 7.3).
 *
 * BORRADOR: editable; el equipo pasa a EN_MANTENIMIENTO.
 * CERRADA: inmutable; recibe el correlativo N° 001-AAAA/OTIC-MANT, el equipo toma
 * el estado y la condición que elige el técnico y se genera el PDF.
 */
final class MantenimientoService
{
    public const ESTADOS_FINALES = ['OPERATIVO', 'INOPERATIVO'];
    public const LICENCIA_MANTENER = 'MANTENER';
    public const LICENCIA_LIBERAR = 'LIBERAR';

    private MantenimientoModel $actas;
    private MantenimientoDetalleModel $detalle;
    private EquipoModel $equipos;
    private ChecklistItemModel $checklist;
    private CatalogoSoftwareModel $software;
    private LicenciaEquipoModel $slots;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->actas = new MantenimientoModel();
        $this->detalle = new MantenimientoDetalleModel();
        $this->equipos = new EquipoModel();
        $this->checklist = new ChecklistItemModel();
        $this->software = new CatalogoSoftwareModel();
        $this->slots = new LicenciaEquipoModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /**
     * Acta completa para la vista y el PDF.
     *
     * @return array{acta: array<string, mixed>, checklist: array<string, list<array<string, mixed>>>, componentes: array<string, array{accion: string, detalle: ?string}>, software: list<array<string, mixed>>}
     */
    public function obtenerCompleta(int $id): array
    {
        $acta = $this->actas->detalle($id);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }

        return [
            'acta'        => $acta,
            'checklist'   => $this->detalle->checklist($id),
            'componentes' => $this->detalle->componentes($id),
            'software'    => $this->detalle->software($id),
        ];
    }

    /**
     * Datos del equipo para iniciar un acta (paso 1 → paso 2).
     *
     * @return array<string, mixed>
     */
    public function equipoParaActa(int $equipoId): array
    {
        $equipo = $this->equipos->ficha($equipoId);
        if ($equipo === null) {
            throw new HttpException(404, 'El equipo no existe.');
        }
        if ($equipo['estado_operativo'] === 'DE_BAJA') {
            throw ValidationException::campo('equipo_id', 'No se puede registrar mantenimiento de un equipo dado de baja.');
        }

        return $equipo;
    }

    /**
     * Crea el acta en BORRADOR (y la cierra si $entrada['accion'] === 'cerrar').
     *
     * @param array<string, mixed> $entrada
     */
    public function crear(array $entrada, int $usuarioId): int
    {
        $cerrar = ($entrada['accion'] ?? '') === 'cerrar';
        $equipoId = (int) ($entrada['equipo_id'] ?? 0);

        $this->db->beginTransaction();
        try {
            $equipo = $this->equipos->findForUpdate($equipoId);
            if ($equipo === null) {
                throw ValidationException::campo('equipo_id', 'Seleccione un equipo válido.');
            }
            if ($equipo['estado_operativo'] === 'DE_BAJA') {
                throw ValidationException::campo('equipo_id', 'No se puede registrar mantenimiento de un equipo dado de baja.');
            }
            $borrador = $this->actas->borradorDeEquipo($equipoId);
            if ($borrador !== null) {
                throw ValidationException::campo('equipo_id', sprintf('El equipo ya tiene un acta en borrador (#%d). Complétela o anúlela.', $borrador));
            }

            $datos = $this->validar($entrada, $equipo, $cerrar, []);
            $previo = $equipo['estado_operativo'] === 'EN_MANTENIMIENTO' ? 'OPERATIVO' : (string) $equipo['estado_operativo'];

            $id = $this->actas->insert([
                'equipo_id'            => $equipoId,
                'personal_id'          => $equipo['personal_id'],
                'oficina_id'           => $equipo['oficina_id'],
                'tecnico_id'           => $datos['tecnico_id'],
                'tipo'                 => $datos['tipo'],
                'estado'               => 'BORRADOR',
                'fecha_ingreso'        => $datos['fecha_ingreso'],
                'fecha_salida'         => $datos['fecha_salida'],
                'problema_reportado'   => $datos['problema_reportado'],
                'observaciones'        => $datos['observaciones'],
                'recomendaciones'      => $datos['recomendaciones'],
                'estado_equipo_previo' => $previo,
                'created_by'           => $usuarioId,
            ]);
            $this->detalle->reemplazar($id, $datos['checklist'], $datos['componentes'], $datos['software']);
            $this->equipos->update($equipoId, ['estado_operativo' => 'EN_MANTENIMIENTO', 'updated_by' => $usuarioId]);
            $this->aplicarDecisionLicencia($id, $equipoId, $datos, $usuarioId);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'mantenimientos', $id, null, $this->instantanea($id), $usuarioId);

            if ($cerrar) {
                $this->cerrarDentroDeTransaccion($id, $equipoId, $datos, $usuarioId);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($cerrar) {
            $this->generarPdfSeguro($id);
        }

        return $id;
    }

    /**
     * Actualiza un acta en BORRADOR (y la cierra si $entrada['accion'] === 'cerrar').
     *
     * @param array<string, mixed> $entrada
     */
    public function actualizar(int $id, array $entrada, int $usuarioId): void
    {
        $cerrar = ($entrada['accion'] ?? '') === 'cerrar';

        $this->db->beginTransaction();
        try {
            $acta = $this->actaBorradorBloqueada($id);
            $equipoId = (int) $acta['equipo_id'];
            $equipo = $this->equipos->findForUpdate($equipoId);
            if ($equipo === null) {
                throw new HttpException(404, 'El equipo del acta no existe.');
            }

            $guardados = array_map(static fn (array $s): int => (int) $s['software_id'], $this->detalle->software($id));
            $antes = $this->instantanea($id);
            $datos = $this->validar($entrada, $equipo, $cerrar, $guardados, (int) $acta['tecnico_id']);

            $this->actas->update($id, [
                'tecnico_id'         => $datos['tecnico_id'],
                'tipo'               => $datos['tipo'],
                'fecha_ingreso'      => $datos['fecha_ingreso'],
                'fecha_salida'       => $datos['fecha_salida'],
                'problema_reportado' => $datos['problema_reportado'],
                'observaciones'      => $datos['observaciones'],
                'recomendaciones'    => $datos['recomendaciones'],
            ]);
            $this->detalle->reemplazar($id, $datos['checklist'], $datos['componentes'], $datos['software']);
            $this->aplicarDecisionLicencia($id, $equipoId, $datos, $usuarioId);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'mantenimientos', $id, $antes, $this->instantanea($id), $usuarioId);

            if ($cerrar) {
                $this->cerrarDentroDeTransaccion($id, $equipoId, $datos, $usuarioId);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        if ($cerrar) {
            $this->generarPdfSeguro($id);
        }
    }

    /**
     * Anula (elimina) un acta en BORRADOR y devuelve el equipo a su estado previo.
     * Solo el autor del borrador o un ADMINISTRADOR.
     */
    public function anular(int $id, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $acta = $this->actaBorradorBloqueada($id);
            if ((int) $acta['created_by'] !== $usuarioId && !Auth::esAdministrador()) {
                throw new HttpException(403, 'Solo el autor del borrador o un administrador puede anularlo.');
            }
            $equipoId = (int) $acta['equipo_id'];
            $equipo = $this->equipos->findForUpdate($equipoId);
            $antes = $this->instantanea($id);

            $this->actas->eliminarBorrador($id);
            if ($equipo !== null && $equipo['estado_operativo'] === 'EN_MANTENIMIENTO') {
                $this->equipos->update($equipoId, ['estado_operativo' => $acta['estado_equipo_previo'], 'updated_by' => $usuarioId]);
            }
            $this->auditoria->registrar(AuditoriaService::ELIMINAR, 'mantenimientos', $id, $antes, null, $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un acta (solo ADMINISTRADOR) que aún no tenga la recepción confirmada.
     * - BORRADOR: igual que anular (el equipo vuelve a su estado previo).
     * - CERRADA: se borran el acta, sus envíos y sus PDF. El estado actual del equipo no
     *   cambia y el número correlativo no se reutiliza. Queda en la auditoría.
     */
    public function eliminar(int $id, int $usuarioId): void
    {
        if (!Auth::esAdministrador()) {
            throw new HttpException(403, 'Solo un administrador puede eliminar actas.');
        }

        $acta = $this->actas->find($id);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }
        if ($acta['estado'] === 'BORRADOR') {
            $this->anular($id, $usuarioId);

            return;
        }

        $this->db->beginTransaction();
        try {
            $acta = $this->actas->findForUpdate($id);
            if ($acta === null) {
                throw new HttpException(404, 'El acta no existe.');
            }
            if ($acta['estado_envio'] === 'RECIBIDO') {
                throw ValidationException::campo('acta', 'No se puede eliminar: el usuario ya confirmó la recepción del acta.');
            }
            $antes = $this->instantanea($id);
            $antes['envios'] = (new MantenimientoEnvioModel())->deActa($id);

            (new MantenimientoEnvioModel())->eliminarDeActa($id);
            $this->actas->eliminarCerradaNoRecibida($id);
            $this->auditoria->registrar(AuditoriaService::ELIMINAR, 'mantenimientos', $id, $antes, null, $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        // Los archivos se borran solo después de confirmar la transacción.
        $pdf = new PdfService();
        foreach ([$acta['pdf_ruta'], $acta['pdf_firmado_ruta']] as $relativa) {
            $ruta = $pdf->rutaAbsoluta($relativa);
            if ($ruta !== null && !@unlink($ruta)) {
                Logger::warning('No se pudo borrar el PDF de un acta eliminada', ['acta' => $id, 'ruta' => $relativa]);
            }
        }
    }

    /**
     * PDF oficial de un acta CERRADA: el firmado digitalmente si existe; si no, el generado.
     *
     * @return array{ruta: string, nombre: string, firmado: bool}
     */
    public function pdf(int $id): array
    {
        $acta = $this->actas->find($id);
        $firmado = $acta !== null ? (new PdfService())->rutaAbsoluta($acta['pdf_firmado_ruta']) : null;
        if ($firmado !== null) {
            return [
                'ruta'    => $firmado,
                'nombre'  => 'Acta_' . str_replace('/', '-', (string) $acta['numero']) . '_firmada.pdf',
                'firmado' => true,
            ];
        }

        return $this->pdfOriginal($id) + ['firmado' => false];
    }

    /**
     * PDF generado (sin firma) de un acta CERRADA: devuelve la ruta del archivo guardado (lo genera si falta).
     *
     * @return array{ruta: string, nombre: string}
     */
    public function pdfOriginal(int $id): array
    {
        $acta = $this->actas->find($id);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }
        if ($acta['estado'] !== 'CERRADA') {
            throw ValidationException::campo('pdf', 'El PDF solo está disponible para actas cerradas.');
        }

        $pdf = new PdfService();
        $ruta = $pdf->rutaAbsoluta($acta['pdf_ruta']);
        if ($ruta === null) {
            $this->generarPdf($id);
            $ruta = $pdf->rutaAbsoluta((string) $this->actas->find($id)['pdf_ruta']);
        }
        if ($ruta === null) {
            throw new HttpException(500, 'No se pudo generar el PDF del acta.');
        }

        return ['ruta' => $ruta, 'nombre' => 'Acta_' . str_replace('/', '-', (string) $acta['numero']) . '.pdf'];
    }

    public function generarPdf(int $id): void
    {
        $datos = $this->obtenerCompleta($id);
        $acta = $datos['acta'];
        $datos['catalogoSoftware'] = $this->software->activos(array_map(static fn (array $s): int => (int) $s['software_id'], $datos['software']));
        $parametros = new ParametroModel();
        $nombreJefe = (string) $parametros->obtener('jefe_otic_nombre', '');
        $datos['jefe'] = [
            // El valor inicial del parámetro es un recordatorio, no un nombre.
            'nombre' => str_starts_with($nombreJefe, 'Registrar ') ? null : $nombreJefe,
            'cargo'  => $parametros->obtener('jefe_otic_cargo'),
        ];

        $pdf = new PdfService();
        // La marca SIGPAT-ACTA-<id> permite reconocer el acta en el PDF que vuelve firmado.
        $contenido = $pdf->generar('acta_mantenimiento', $datos, 'Acta de mantenimiento N° ' . $acta['numero'], [
            'Keywords' => ActaFirmaService::marca($id),
        ]);
        $relativa = $pdf->guardar($contenido, 'actas', 'acta_' . $acta['id'] . '_' . str_replace('/', '-', (string) $acta['numero']) . '.pdf');
        $this->actas->update($id, ['pdf_ruta' => $relativa]);
    }

    /** Debe llamarse dentro de la transacción del acta. */
    private function cerrarDentroDeTransaccion(int $id, int $equipoId, array $datos, int $usuarioId): void
    {
        $numero = (new CorrelativoService())->siguiente(CorrelativoService::MANTENIMIENTO);
        $ahora = date('Y-m-d H:i:s');

        $this->actas->update($id, [
            'numero'              => $numero['numero'],
            'anio'                => $numero['anio'],
            'correlativo'         => $numero['correlativo'],
            'estado'              => 'CERRADA',
            'fecha_salida'        => $datos['fecha_salida'],
            'estado_equipo_final' => $datos['estado_equipo_final'],
            'condicion_final'     => $datos['condicion_final'],
            'cerrado_por'         => $usuarioId,
            'cerrado_at'          => $ahora,
        ]);
        $this->equipos->update($equipoId, [
            'estado_operativo' => $datos['estado_equipo_final'],
            'condicion_fisica' => $datos['condicion_final'],
            'updated_by'       => $usuarioId,
        ]);
        $this->auditoria->registrar(
            AuditoriaService::CERRAR,
            'mantenimientos',
            $id,
            ['estado' => 'BORRADOR'],
            ['estado' => 'CERRADA', 'numero' => $numero['numero'], 'estado_equipo_final' => $datos['estado_equipo_final'], 'condicion_final' => $datos['condicion_final']],
            $usuarioId
        );
    }

    /** Si se formateó y se eligió liberar, libera la instalación de Microsoft 365 con motivo FORMATEO. */
    private function aplicarDecisionLicencia(int $actaId, int $equipoId, array $datos, int $usuarioId): void
    {
        if (!$datos['formateo'] || $datos['licencia_accion'] !== self::LICENCIA_LIBERAR) {
            return;
        }
        $slot = $this->slots->activoDeEquipo($equipoId, true);
        if ($slot !== null) {
            (new LicenciaService())->liberarSlot((int) $slot['id'], 'FORMATEO', $usuarioId, 'Formateo registrado en el acta de mantenimiento #' . $actaId);
        }
    }

    /** El PDF se genera tras el commit; si falla, se regenerará al descargarlo. */
    private function generarPdfSeguro(int $id): void
    {
        try {
            $this->generarPdf($id);
        } catch (Throwable $e) {
            Logger::exception($e, 'No se pudo generar el PDF del acta #' . $id);
        }
    }

    /** @return array<string, mixed> */
    private function actaBorradorBloqueada(int $id): array
    {
        $acta = $this->actas->findForUpdate($id);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }
        if ($acta['estado'] !== 'BORRADOR') {
            throw ValidationException::campo('estado', 'El acta está cerrada y no puede modificarse.');
        }

        return $acta;
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, mixed> $equipo
     * @param list<int> $softwareGuardado
     * @return array<string, mixed>
     */
    private function validar(array $e, array $equipo, bool $cerrar, array $softwareGuardado, ?int $tecnicoActual = null): array
    {
        $txt = static fn (string $c): string => is_scalar($e[$c] ?? null) ? trim((string) $e[$c]) : '';
        $arr = static fn (string $c): array => is_array($e[$c] ?? null) ? $e[$c] : [];
        $errores = [];

        $tipo = $txt('tipo');
        if (!in_array($tipo, MantenimientoModel::TIPOS, true)) {
            $errores['tipo'] = 'Seleccione el tipo de mantenimiento.';
        }

        // Técnico: el usuario en sesión; solo un ADMINISTRADOR puede elegir otro.
        $tecnicoId = Auth::id() ?? 0;
        if (Auth::esAdministrador() && ctype_digit($txt('tecnico_id'))) {
            $tecnicoId = (int) $txt('tecnico_id');
            $tecnico = (new UsuarioSistemaModel())->find($tecnicoId);
            if ($tecnico === null || ((int) $tecnico['activo'] !== 1 && $tecnicoId !== $tecnicoActual)) {
                $errores['tecnico_id'] = 'Seleccione un técnico activo.';
            }
        } elseif ($tecnicoActual !== null && !Auth::esAdministrador()) {
            $tecnicoId = $tecnicoActual;
        }

        $ingreso = $this->fechaHora($txt('fecha_ingreso'));
        $salida = $this->fechaHora($txt('fecha_salida'));
        $limite = new DateTimeImmutable('+10 minutes');
        if ($ingreso === false || $ingreso === null) {
            $errores['fecha_ingreso'] = 'Ingrese la fecha y hora de ingreso del equipo.';
        } elseif ($ingreso > $limite) {
            $errores['fecha_ingreso'] = 'La fecha de ingreso no puede ser futura.';
        }
        if ($salida === false) {
            $errores['fecha_salida'] = 'Fecha y hora de salida no válida.';
        } elseif ($salida !== null) {
            if ($salida > $limite) {
                $errores['fecha_salida'] = 'La fecha de salida no puede ser futura.';
            } elseif ($ingreso instanceof DateTimeImmutable && $salida < $ingreso) {
                $errores['fecha_salida'] = 'La salida no puede ser anterior al ingreso.';
            }
        } elseif ($cerrar) {
            $errores['fecha_salida'] = 'Para cerrar el acta indique la fecha y hora de salida.';
        }

        $textos = [];
        foreach (['problema_reportado', 'observaciones', 'recomendaciones'] as $campo) {
            $valor = $txt($campo);
            if (mb_strlen($valor) > 2000) {
                $errores[$campo] = 'Máximo 2000 caracteres.';
            }
            $textos[$campo] = $valor === '' ? null : $valor;
        }

        // Checklist: se guardan todos los ítems aplicables, con realizado 0/1.
        $marcados = array_map('intval', array_filter($arr('checklist'), 'is_scalar'));
        $checklist = [];
        $formateo = false;
        foreach ($this->checklist->activosPorTipo((string) $equipo['tipo']) as $items) {
            foreach ($items as $item) {
                $realizado = in_array((int) $item['id'], $marcados, true) ? 1 : 0;
                $checklist[] = ['checklist_item_id' => (int) $item['id'], 'realizado' => $realizado];
                if ($realizado === 1 && $item['codigo'] === ChecklistItemModel::CODIGO_FORMATEO) {
                    $formateo = true;
                }
            }
        }

        // Componentes
        $acciones = $arr('comp_accion');
        $detalles = $arr('comp_detalle');
        $componentes = [];
        foreach (MantenimientoDetalleModel::COMPONENTES as $c) {
            $accion = is_scalar($acciones[$c] ?? null) ? (string) $acciones[$c] : 'NO_APLICA';
            $detalle = is_scalar($detalles[$c] ?? null) ? trim((string) $detalles[$c]) : '';
            if (!in_array($accion, MantenimientoDetalleModel::ACCIONES, true)) {
                $errores['componentes'] = 'Acción no válida para el componente ' . $c . '.';
                $accion = 'NO_APLICA';
            }
            if ($accion !== 'NO_APLICA' && $detalle === '') {
                $errores['componentes'] = sprintf('Indique la serie o capacidad del componente %s (%s).', $c, etiqueta($accion));
            }
            if (mb_strlen($detalle) > 150) {
                $errores['componentes'] = 'El detalle de ' . $c . ' admite como máximo 150 caracteres.';
            }
            $componentes[$c] = ['accion' => $accion, 'detalle' => $detalle === '' ? null : $detalle];
        }

        // Software (no aplica a impresoras)
        $software = [];
        if ($equipo['tipo'] !== 'IMPRESORA') {
            $validos = array_map(static fn (array $s): int => (int) $s['id'], $this->software->activos($softwareGuardado));
            $versiones = $arr('software_version');
            foreach (array_unique(array_map('intval', array_filter($arr('software'), 'is_scalar'))) as $sid) {
                if (!in_array($sid, $validos, true)) {
                    $errores['software'] = 'Hay un software no válido en la selección.';
                    continue;
                }
                $version = is_scalar($versiones[$sid] ?? null) ? trim((string) $versiones[$sid]) : '';
                if (mb_strlen($version) > 40) {
                    $errores['software'] = 'La versión admite como máximo 40 caracteres.';
                }
                $software[] = ['software_id' => $sid, 'version' => $version === '' ? null : $version];
            }
        }

        // Licencia Microsoft 365 al formatear
        $licenciaAccion = $txt('licencia_accion');
        if ($formateo && $this->slots->activoDeEquipo((int) $equipo['id']) !== null
            && !in_array($licenciaAccion, [self::LICENCIA_MANTENER, self::LICENCIA_LIBERAR], true)) {
            $errores['licencia_accion'] = 'Se marcó el formateo y el equipo ocupa una instalación de Microsoft 365: indique si se libera o se mantiene.';
        }

        // Cierre
        $estadoFinal = $txt('estado_equipo_final');
        $condicionFinal = $txt('condicion_final');
        if ($cerrar) {
            if (!in_array($estadoFinal, self::ESTADOS_FINALES, true)) {
                $errores['estado_equipo_final'] = 'Indique el estado en que queda el equipo.';
            }
            if (!in_array($condicionFinal, EquipoModel::CONDICIONES, true)) {
                $errores['condicion_final'] = 'Indique la condición física en que queda el equipo.';
            }
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return [
            'tipo'                => $tipo,
            'tecnico_id'          => $tecnicoId,
            'fecha_ingreso'       => $ingreso->format('Y-m-d H:i:s'),
            'fecha_salida'        => $salida?->format('Y-m-d H:i:s'),
            'problema_reportado'  => $textos['problema_reportado'],
            'observaciones'       => $textos['observaciones'],
            'recomendaciones'     => $textos['recomendaciones'],
            'checklist'           => $checklist,
            'componentes'         => $componentes,
            'software'            => $software,
            'formateo'            => $formateo,
            'licencia_accion'     => $licenciaAccion,
            'estado_equipo_final' => $cerrar ? $estadoFinal : null,
            'condicion_final'     => $cerrar ? $condicionFinal : null,
        ];
    }

    /** Acepta "Y-m-d\TH:i" (datetime-local) o "Y-m-d H:i[:s]". null si vacío, false si no es válida. */
    private function fechaHora(string $valor): DateTimeImmutable|false|null
    {
        if ($valor === '') {
            return null;
        }
        foreach (['!Y-m-d\TH:i', '!Y-m-d\TH:i:s', '!Y-m-d H:i', '!Y-m-d H:i:s'] as $formato) {
            $fecha = DateTimeImmutable::createFromFormat($formato, $valor);
            if ($fecha !== false && (int) $fecha->format('Y') >= 2000) {
                return $fecha;
            }
        }

        return false;
    }

    /** @return array<string, mixed> */
    private function instantanea(int $id): array
    {
        $acta = $this->actas->find($id) ?? [];
        $acta['checklist_realizado'] = [];
        foreach ($this->detalle->checklist($id) as $items) {
            foreach ($items as $i) {
                if ((int) $i['realizado'] === 1) {
                    $acta['checklist_realizado'][] = $i['descripcion'];
                }
            }
        }
        $acta['componentes'] = $this->detalle->componentes($id);
        $acta['software'] = array_column($this->detalle->software($id), 'nombre');

        return $acta;
    }
}
