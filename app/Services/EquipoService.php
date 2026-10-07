<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\EquipoCodigoModel;
use App\Models\EquipoModel;
use App\Models\EquipoPuntoRedModel;
use App\Models\LicenciaEquipoModel;
use App\Models\OficinaModel;
use App\Models\ParametroModel;
use App\Models\PersonalModel;
use DateTimeImmutable;
use Throwable;

/**
 * Inventario de equipos: alta, edición, eliminación de altas erróneas y baja definitiva.
 */
final class EquipoService
{
    /** Campos de hardware que deben ser NULL en impresoras (sección 7.1). */
    public const CAMPOS_HARDWARE = ['procesador', 'ram_gb', 'disco_tipo', 'disco_capacidad_gb', 'sistema_operativo'];
    public const DISCOS = ['HDD', 'SSD', 'NVME'];

    /** Estados que pueden elegirse en el formulario (EN_MANTENIMIENTO lo gestionan las actas; DE_BAJA, la baja). */
    public const ESTADOS_EDITABLES = ['OPERATIVO', 'INOPERATIVO'];

    private const ETIQUETAS_UNICAS = [
        'nro_serie'          => 'número de serie',
        'codigo_patrimonial' => 'código patrimonial',
        'codigo_interno'     => 'código interno',
        'hostname'           => 'hostname',
        'mac_lan'            => 'MAC LAN',
        'ip_lan'             => 'IP',
        'mac_wifi'           => 'MAC Wi-Fi',
    ];

    private EquipoModel $equipos;
    private EquipoCodigoModel $codigos;
    private EquipoPuntoRedModel $puntos;
    private LicenciaEquipoModel $licencias;
    private OficinaModel $oficinas;
    private PersonalModel $personal;
    private AsignacionService $asignacion;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->equipos = new EquipoModel();
        $this->codigos = new EquipoCodigoModel();
        $this->puntos = new EquipoPuntoRedModel();
        $this->licencias = new LicenciaEquipoModel();
        $this->oficinas = new OficinaModel();
        $this->personal = new PersonalModel();
        $this->asignacion = new AsignacionService();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $equipo = $this->equipos->find($id);
        if ($equipo === null) {
            throw new HttpException(404, 'El equipo no existe.');
        }

        return $equipo;
    }

    public function vidaUtilPorDefecto(): int
    {
        return (new ParametroModel())->obtenerEntero('vida_util_meses_default', 48);
    }

    /** @param array<string, mixed> $entrada */
    public function crear(array $entrada, int $usuarioId): int
    {
        ['datos' => $datos, 'codigos' => $codigos, 'puntos' => $puntos] = $this->validar($entrada, null);
        $datos['created_by'] = $usuarioId;
        $datos['updated_by'] = $usuarioId;

        $this->db->beginTransaction();
        try {
            $id = $this->equipos->insert($datos);
            $this->codigos->reemplazar($id, $codigos);
            $this->puntos->reemplazar($id, $puntos);
            $this->asignacion->registrarAlta($id, (int) $datos['oficina_id'], $datos['personal_id'], $usuarioId);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'equipos', $id, null, $this->instantanea($id), $usuarioId);
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
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
            $actual = $this->equipos->findForUpdate($id);
            if ($actual === null) {
                throw new HttpException(404, 'El equipo no existe.');
            }
            if ($actual['estado_operativo'] === 'DE_BAJA') {
                throw ValidationException::campo('estado_operativo', 'Un equipo dado de baja no puede modificarse.');
            }

            ['datos' => $datos, 'codigos' => $codigos, 'puntos' => $puntos] = $this->validar($entrada, $actual);
            $antes = $this->instantanea($id);

            $oficinaId = (int) $datos['oficina_id'];
            $personalId = $datos['personal_id'];
            unset($datos['oficina_id'], $datos['personal_id']);
            $datos['updated_by'] = $usuarioId;

            $this->equipos->update($id, $datos);
            $this->codigos->reemplazar($id, $codigos);
            $this->puntos->reemplazar($id, $puntos);

            $personalActual = $actual['personal_id'] === null ? null : (int) $actual['personal_id'];
            if ($personalId !== $personalActual || $oficinaId !== (int) $actual['oficina_id']) {
                $motivo = match (true) {
                    $personalId !== $personalActual && $personalId === null => AsignacionService::MOTIVO_DESVINCULACION,
                    $personalId !== $personalActual                        => AsignacionService::MOTIVO_ASIGNACION,
                    default                                                 => AsignacionService::MOTIVO_TRASLADO,
                };
                $this->asignacion->asignar($id, $oficinaId, $personalId, $motivo, $usuarioId, 'Cambio desde la ficha del equipo');
            }

            $this->auditoria->registrar(AuditoriaService::EDITAR, 'equipos', $id, $antes, $this->instantanea($id), $usuarioId);
            $this->db->commit();
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Elimina un equipo registrado por error. Solo si no tiene actas, informes
     * ni licencias (ni siquiera liberadas); en otro caso corresponde la baja.
     */
    public function eliminar(int $id, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $equipo = $this->equipos->findForUpdate($id);
            if ($equipo === null) {
                throw new HttpException(404, 'El equipo no existe.');
            }
            if ($this->equipos->tieneDocumentosRelacionados($id)) {
                throw ValidationException::campo('equipo', 'No se puede eliminar: el equipo tiene actas, informes o licencias registradas. Use la baja definitiva.');
            }

            $antes = $this->instantanea($id);
            $this->equipos->eliminarDefinitivo($id);
            $this->auditoria->registrar(AuditoriaService::ELIMINAR, 'equipos', $id, $antes, null, $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Baja definitiva (solo con recomendado_baja = 1): pasa a DE_BAJA, libera su
     * slot de Office con motivo BAJA, desvincula al responsable y audita; todo
     * en una transacción.
     */
    public function confirmarBaja(int $id, int $usuarioId, ?string $observacion): void
    {
        $this->db->beginTransaction();
        try {
            $equipo = $this->equipos->findForUpdate($id);
            if ($equipo === null) {
                throw new HttpException(404, 'El equipo no existe.');
            }
            if ($equipo['estado_operativo'] === 'DE_BAJA') {
                throw ValidationException::campo('baja', 'El equipo ya está dado de baja.');
            }
            if ((int) $equipo['recomendado_baja'] !== 1) {
                throw ValidationException::campo('baja', 'Solo se puede dar de baja un equipo con recomendación de baja en un informe técnico emitido.');
            }
            if ($this->equipos->tieneActaAbierta($id)) {
                throw ValidationException::campo('baja', 'El equipo tiene un acta de mantenimiento en borrador. Ciérrela antes de darlo de baja.');
            }

            $antes = $this->instantanea($id);
            $texto = 'Baja definitiva del equipo' . ($observacion !== null && $observacion !== '' ? ': ' . $observacion : '');

            $slot = $this->licencias->activoDeEquipo($id, true);
            if ($slot !== null) {
                (new LicenciaService())->liberarSlot((int) $slot['id'], 'BAJA', $usuarioId, mb_substr($texto, 0, 255));
            }

            $this->asignacion->asignar($id, (int) $equipo['oficina_id'], null, AsignacionService::MOTIVO_BAJA, $usuarioId, $texto, true);

            $this->equipos->update($id, [
                'estado_operativo' => 'DE_BAJA',
                'fecha_baja'       => date('Y-m-d'),
                'personal_id'      => null,
                'updated_by'       => $usuarioId,
            ]);

            $this->auditoria->registrar(AuditoriaService::BAJA, 'equipos', $id, $antes, $this->instantanea($id) + ['observacion_baja' => $observacion], $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Normaliza una MAC a "AA:BB:CC:DD:EE:FF". Devuelve null si está vacía y false si no es válida. */
    public static function normalizarMac(?string $mac): string|false|null
    {
        $mac = trim((string) $mac);
        if ($mac === '') {
            return null;
        }
        $hex = strtoupper((string) preg_replace('/[\s:\-.]/', '', $mac));
        if (preg_match('/^[0-9A-F]{12}$/', $hex) !== 1) {
            return false;
        }

        return implode(':', str_split($hex, 2));
    }

    /** Valida una IPv4/IPv6 y la devuelve en forma canónica. null si vacía, false si no es válida. */
    public static function normalizarIp(?string $ip): string|false|null
    {
        $ip = trim((string) $ip);
        if ($ip === '') {
            return null;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return $ip;
        }
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            $binario = inet_pton($ip);

            return $binario === false ? false : strtolower((string) inet_ntop($binario));
        }

        return false;
    }

    /**
     * @param array<string, mixed> $e entrada del formulario
     * @param array<string, mixed>|null $actual fila actual (edición) o null (alta)
     * @return array{datos: array<string, mixed>, codigos: list<array{anio: int, codigo: string}>, puntos: list<array{codigo_punto: string, switch_puerto: ?string, vlan: ?string, observacion: ?string}>}
     */
    private function validar(array $e, ?array $actual): array
    {
        $id = $actual === null ? null : (int) $actual['id'];
        $txt = static fn (string $c): string => is_scalar($e[$c] ?? null) ? trim(preg_replace('/\s+/u', ' ', (string) $e[$c]) ?? '') : '';
        $nulo = static fn (string $c): ?string => $txt($c) === '' ? null : $txt($c);
        $errores = [];

        $tipo = $txt('tipo');
        if (!in_array($tipo, EquipoModel::TIPOS, true)) {
            $errores['tipo'] = 'Seleccione el tipo de equipo.';
        }
        $esImpresora = $tipo === 'IMPRESORA';

        $datos = [
            'tipo'               => $tipo,
            'marca'              => $txt('marca'),
            'modelo'             => $txt('modelo'),
            'nro_serie'          => $nulo('nro_serie') === null ? null : mb_strtoupper($txt('nro_serie')),
            'codigo_patrimonial' => $nulo('codigo_patrimonial') === null ? null : mb_strtoupper($txt('codigo_patrimonial')),
            'codigo_interno'     => $nulo('codigo_interno') === null ? null : mb_strtoupper($txt('codigo_interno')),
            'observaciones'      => $nulo('observaciones'),
            'orden_compra'       => $nulo('orden_compra'),
            'proveedor'          => $nulo('proveedor'),
        ];

        if ($datos['marca'] === '' || mb_strlen($datos['marca']) > 60) {
            $errores['marca'] = 'Ingrese la marca (máximo 60 caracteres).';
        }
        if ($datos['modelo'] === '' || mb_strlen($datos['modelo']) > 100) {
            $errores['modelo'] = 'Ingrese el modelo (máximo 100 caracteres).';
        }
        foreach (['nro_serie' => 80, 'codigo_patrimonial' => 30, 'codigo_interno' => 30, 'orden_compra' => 50, 'proveedor' => 150] as $campo => $max) {
            if ($datos[$campo] !== null && mb_strlen($datos[$campo]) > $max) {
                $errores[$campo] = sprintf('Máximo %d caracteres.', $max);
            }
        }
        if ($datos['observaciones'] !== null && mb_strlen($datos['observaciones']) > 2000) {
            $errores['observaciones'] = 'Las observaciones admiten como máximo 2000 caracteres.';
        }

        // Hardware (NULL para impresoras)
        if ($esImpresora) {
            foreach (self::CAMPOS_HARDWARE as $campo) {
                $datos[$campo] = null;
            }
        } else {
            $datos['procesador'] = $nulo('procesador');
            $datos['sistema_operativo'] = $nulo('sistema_operativo');
            $datos['ram_gb'] = $this->entero($txt('ram_gb'), 1, 1024, 'ram_gb', 'La RAM debe ser un número entre 1 y 1024 GB.', $errores);
            $datos['disco_capacidad_gb'] = $this->entero($txt('disco_capacidad_gb'), 1, 100000, 'disco_capacidad_gb', 'La capacidad debe ser un número entre 1 y 100000 GB.', $errores);
            $disco = $txt('disco_tipo');
            $datos['disco_tipo'] = $disco === '' ? null : $disco;
            if ($datos['disco_tipo'] !== null && !in_array($datos['disco_tipo'], self::DISCOS, true)) {
                $errores['disco_tipo'] = 'Tipo de disco no válido.';
            }
            if ($datos['procesador'] !== null && mb_strlen($datos['procesador']) > 120) {
                $errores['procesador'] = 'Máximo 120 caracteres.';
            }
            if ($datos['sistema_operativo'] !== null && mb_strlen($datos['sistema_operativo']) > 80) {
                $errores['sistema_operativo'] = 'Máximo 80 caracteres.';
            }
        }

        // Red
        $hostname = $txt('hostname');
        $datos['hostname'] = $hostname === '' ? null : mb_strtoupper($hostname);
        if ($datos['hostname'] !== null && preg_match('/^[A-Z0-9](?:[A-Z0-9\-]{0,61}[A-Z0-9])?$/', $datos['hostname']) !== 1) {
            $errores['hostname'] = 'Hostname no válido: solo letras, números y guiones (máx. 63), sin empezar ni terminar en guion.';
        }
        $ip = self::normalizarIp($txt('ip_lan'));
        if ($ip === false) {
            $errores['ip_lan'] = 'La IP no es una dirección IPv4 o IPv6 válida.';
        }
        $datos['ip_lan'] = $ip === false ? null : $ip;
        foreach (['mac_lan' => 'MAC LAN', 'mac_wifi' => 'MAC Wi-Fi'] as $campo => $etiqueta) {
            $mac = self::normalizarMac($txt($campo));
            if ($mac === false) {
                $errores[$campo] = $etiqueta . ' no válida. Use 12 dígitos hexadecimales (p. ej. 00:1A:2B:3C:4D:5E).';
            }
            $datos[$campo] = $mac === false ? null : $mac;
        }
        if ($datos['mac_lan'] !== null && $datos['mac_lan'] === $datos['mac_wifi']) {
            $errores['mac_wifi'] = 'La MAC Wi-Fi no puede ser igual a la MAC LAN.';
        }

        // Unicidad con mensajes claros
        foreach (self::ETIQUETAS_UNICAS as $campo => $etiqueta) {
            if ($datos[$campo] === null || isset($errores[$campo])) {
                continue;
            }
            $otro = $this->equipos->otroConValor($campo, (string) $datos[$campo], $id);
            if ($otro !== null) {
                $errores[$campo] = sprintf(
                    '%s "%s" ya registrado en el equipo #%d (%s %s %s).',
                    mb_strtoupper(mb_substr($etiqueta, 0, 1)) . mb_substr($etiqueta, 1),
                    $datos[$campo],
                    $otro['id'],
                    $otro['tipo'],
                    $otro['marca'],
                    $otro['modelo']
                );
            }
        }

        // Estado y condición
        $estado = $txt('estado_operativo');
        if ($actual !== null && $actual['estado_operativo'] === 'EN_MANTENIMIENTO') {
            $datos['estado_operativo'] = 'EN_MANTENIMIENTO';
        } elseif (in_array($estado, self::ESTADOS_EDITABLES, true)) {
            $datos['estado_operativo'] = $estado;
        } else {
            $errores['estado_operativo'] = 'Seleccione un estado operativo válido.';
        }
        $condicion = $txt('condicion_fisica');
        if (in_array($condicion, EquipoModel::CONDICIONES, true)) {
            $datos['condicion_fisica'] = $condicion;
        } else {
            $errores['condicion_fisica'] = 'Seleccione la condición física.';
        }

        // Ciclo de vida
        $datos['fecha_adquisicion'] = $this->fecha($txt('fecha_adquisicion'), 'fecha_adquisicion', $errores, true);
        $datos['garantia_hasta'] = $this->fecha($txt('garantia_hasta'), 'garantia_hasta', $errores, false);
        $vida = $this->entero($txt('vida_util_meses'), 1, 600, 'vida_util_meses', 'La vida útil debe estar entre 1 y 600 meses.', $errores);
        $datos['vida_util_meses'] = $vida ?? $this->vidaUtilPorDefecto();
        $datos['periodicidad_mant_meses'] = $this->entero($txt('periodicidad_mant_meses'), 1, 60, 'periodicidad_mant_meses', 'La periodicidad debe estar entre 1 y 60 meses.', $errores);
        $valor = str_replace(',', '', $txt('valor_adquisicion'));
        if ($valor === '') {
            $datos['valor_adquisicion'] = null;
        } elseif (preg_match('/^\d{1,10}(\.\d{1,2})?$/', $valor) === 1) {
            $datos['valor_adquisicion'] = $valor;
        } else {
            $datos['valor_adquisicion'] = null;
            $errores['valor_adquisicion'] = 'Ingrese un monto válido (hasta 2 decimales).';
        }

        // Asignación
        $oficinaId = ctype_digit($txt('oficina_id')) ? (int) $txt('oficina_id') : 0;
        $oficina = $oficinaId > 0 ? $this->oficinas->find($oficinaId) : null;
        $oficinaActual = $actual === null ? null : (int) $actual['oficina_id'];
        if ($oficina === null) {
            $errores['oficina_id'] = 'Seleccione la oficina donde se ubica el equipo.';
        } elseif ((int) $oficina['activo'] !== 1 && $oficinaId !== $oficinaActual) {
            $errores['oficina_id'] = 'La oficina seleccionada está inactiva.';
        }
        $datos['oficina_id'] = $oficinaId;

        $personalId = ctype_digit($txt('personal_id')) ? (int) $txt('personal_id') : null;
        if ($personalId !== null) {
            $persona = $this->personal->find($personalId);
            $personalActual = $actual === null || $actual['personal_id'] === null ? null : (int) $actual['personal_id'];
            if ($persona === null || ((int) $persona['activo'] !== 1 && $personalId !== $personalActual)) {
                $errores['personal_id'] = 'El responsable seleccionado no existe o está inactivo.';
            }
        }
        $datos['personal_id'] = $personalId;

        // Filas dinámicas
        $codigos = $this->validarCodigos($e, $id, $errores);
        $puntos = $this->validarPuntos($e, $errores);

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return ['datos' => $datos, 'codigos' => $codigos, 'puntos' => $puntos];
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, string> $errores
     * @return list<array{anio: int, codigo: string}>
     */
    private function validarCodigos(array $e, ?int $id, array &$errores): array
    {
        $anios = is_array($e['codigos_anio'] ?? null) ? array_values($e['codigos_anio']) : [];
        $valores = is_array($e['codigos_codigo'] ?? null) ? array_values($e['codigos_codigo']) : [];
        $filas = [];
        $vistos = [];

        foreach ($anios as $i => $anioTexto) {
            $anioTexto = is_scalar($anioTexto) ? trim((string) $anioTexto) : '';
            $codigo = is_scalar($valores[$i] ?? null) ? mb_strtoupper(trim((string) $valores[$i])) : '';
            if ($anioTexto === '' && $codigo === '') {
                continue;
            }
            if (preg_match('/^\d{4}$/', $anioTexto) !== 1 || (int) $anioTexto < 1990 || (int) $anioTexto > 2100) {
                $errores['codigos'] = 'Cada código anual necesita un año válido (1990-2100).';
                continue;
            }
            if ($codigo === '' || mb_strlen($codigo) > 40) {
                $errores['codigos'] = 'Ingrese el código de inventario del año ' . $anioTexto . ' (máximo 40 caracteres).';
                continue;
            }
            $anio = (int) $anioTexto;
            if (isset($vistos[$anio])) {
                $errores['codigos'] = 'El año ' . $anio . ' está repetido en los códigos de inventario.';
                continue;
            }
            $vistos[$anio] = true;
            $otro = (new EquipoCodigoModel())->equipoConCodigo($anio, $codigo, $id);
            if ($otro !== null) {
                $errores['codigos'] = sprintf('El código %s del año %d ya pertenece al equipo #%d.', $codigo, $anio, $otro);
                continue;
            }
            $filas[] = ['anio' => $anio, 'codigo' => $codigo];
        }

        return $filas;
    }

    /**
     * @param array<string, mixed> $e
     * @param array<string, string> $errores
     * @return list<array{codigo_punto: string, switch_puerto: ?string, vlan: ?string, observacion: ?string}>
     */
    private function validarPuntos(array $e, array &$errores): array
    {
        $col = static fn (string $c): array => is_array($e[$c] ?? null) ? array_values($e[$c]) : [];
        $codigos = $col('puntos_codigo');
        $switches = $col('puntos_switch');
        $vlans = $col('puntos_vlan');
        $obs = $col('puntos_obs');
        $limpio = static fn (mixed $v): ?string => is_scalar($v) && trim((string) $v) !== '' ? trim((string) $v) : null;

        $filas = [];
        $vistos = [];
        foreach ($codigos as $i => $codigo) {
            $codigo = $limpio($codigo);
            $switch = $limpio($switches[$i] ?? null);
            $vlan = $limpio($vlans[$i] ?? null);
            $observacion = $limpio($obs[$i] ?? null);
            if ($codigo === null && $switch === null && $vlan === null && $observacion === null) {
                continue;
            }
            if ($codigo === null || mb_strlen($codigo) > 30) {
                $errores['puntos_red'] = 'Cada punto de red necesita su código o rotulado (máximo 30 caracteres).';
                continue;
            }
            $codigo = mb_strtoupper($codigo);
            if (isset($vistos[$codigo])) {
                $errores['puntos_red'] = 'El punto de red ' . $codigo . ' está repetido.';
                continue;
            }
            if (($switch !== null && mb_strlen($switch) > 60) || ($vlan !== null && mb_strlen($vlan) > 20) || ($observacion !== null && mb_strlen($observacion) > 255)) {
                $errores['puntos_red'] = 'Revise la longitud de los datos del punto de red ' . $codigo . '.';
                continue;
            }
            $vistos[$codigo] = true;
            $filas[] = ['codigo_punto' => $codigo, 'switch_puerto' => $switch, 'vlan' => $vlan, 'observacion' => $observacion];
        }

        return $filas;
    }

    /** @param array<string, string> $errores */
    private function entero(string $valor, int $min, int $max, string $campo, string $mensaje, array &$errores): ?int
    {
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d+$/', $valor) !== 1 || (int) $valor < $min || (int) $valor > $max) {
            $errores[$campo] = $mensaje;

            return null;
        }

        return (int) $valor;
    }

    /** @param array<string, string> $errores */
    private function fecha(string $valor, string $campo, array &$errores, bool $noFutura): ?string
    {
        if ($valor === '') {
            return null;
        }
        $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);
        if ($fecha === false || $fecha->format('Y-m-d') !== $valor || (int) $fecha->format('Y') < 1990) {
            $errores[$campo] = 'Ingrese una fecha válida.';

            return null;
        }
        if ($noFutura && $fecha > new DateTimeImmutable('today')) {
            $errores[$campo] = 'La fecha no puede ser futura.';

            return null;
        }

        return $valor;
    }

    /** @return array<string, mixed> */
    private function instantanea(int $id): array
    {
        $fila = $this->equipos->find($id) ?? [];
        $fila['codigos'] = $this->codigos->porEquipo($id);
        $fila['puntos_red'] = $this->puntos->porEquipo($id);

        return $fila;
    }

    private function traducirError(DatabaseException $e): Throwable
    {
        if (!$e->esViolacionIntegridad()) {
            return $e;
        }
        $constraint = (string) $e->getConstraint();
        foreach (self::ETIQUETAS_UNICAS as $campo => $etiqueta) {
            if ($constraint === 'uq_equipos_' . $campo) {
                return ValidationException::campo($campo, 'Ya existe otro equipo con ese ' . $etiqueta . '.');
            }
        }

        return match ($constraint) {
            'uq_equipo_codigos_anio_codigo', 'uq_equipo_codigos_equipo_anio' => ValidationException::campo('codigos', 'Hay un código anual de inventario repetido.'),
            'uq_equipo_puntos_red_equipo_punto' => ValidationException::campo('puntos_red', 'Hay un punto de red repetido.'),
            'chk_equipos_hardware_impresora' => ValidationException::campo('tipo', 'Una impresora no puede tener procesador, RAM ni disco.'),
            default => $e,
        };
    }
}
