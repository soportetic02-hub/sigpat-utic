<?php

declare(strict_types=1);

/*
 * Importa al sistema el inventario de la hoja de cálculo "INVENTARIO 2026.xlsx".
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\xampp\php\php.exe database\importar_excel.php "C:\ruta\INVENTARIO 2026.xlsx"             -> simulación (no guarda nada)
 *   C:\xampp\php\php.exe database\importar_excel.php "C:\ruta\INVENTARIO 2026.xlsx" --ejecutar  -> guarda los datos
 *
 * Opciones:
 *   --ejecutar         confirma la transacción (sin esta opción todo se revierte al final).
 *   --usuario=admin    usuario del sistema que figurará como autor en la auditoría (por defecto "admin").
 *   --forzar           permite volver a importar aunque ya existan equipos importados desde Excel.
 *
 * Hojas que se importan:
 *   PCS ASIGNADAS     -> oficinas, personal y equipos PC/LAPTOP (operativos).
 *   EQUIPOS DE BAJA   -> equipos PC/LAPTOP INOPERATIVOS ubicados en el Almacén OTIC.
 *   IMPRESORAS        -> equipos IMPRESORA.
 * Hojas que no se importan: "Discos Almacen" (no hay tabla de repuestos), "SWITCH Y AP" (el
 * inventario solo admite PC, LAPTOP e IMPRESORA), "Tabla dinámica 1" y "Hoja 8" (resúmenes).
 *
 * Todo se hace en UNA transacción usando los servicios del sistema (mismas validaciones,
 * historial de asignaciones y auditoría que el formulario web): o se importa todo o nada.
 */

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\ValidationException;
use App\Models\EquipoCodigoModel;
use App\Models\EquipoModel;
use App\Services\EquipoService;
use App\Services\OficinaService;
use App\Services\PersonalService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';
require __DIR__ . '/LectorXlsx.php';

set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if ((error_reporting() & $nivel) === 0) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

final class ImportadorInventario
{
    private const MARCA = '[Importado de Excel';

    /**
     * Catálogo de oficinas: clave => [nombre, tipo, clave del padre, siglas, alias del Excel].
     * Los alias se comparan sin tildes, sin signos y en mayúsculas. Un nombre del Excel que no
     * esté aquí se crea bajo la dirección de su fila y se informa en el reporte.
     */
    private const OFICINAS = [
        'DA'    => ['Dirección Académica', 'DIRECCION', null, null, ['UNIDAD DE GESTION ACADEMICA']],
        'DADM'  => ['Dirección Administrativa', 'DIRECCION', null, null, ['UNIDAD DE GESTION ADMINISTRATIVA']],
        'DI'    => ['Dirección de Investigación', 'DIRECCION', null, null, ['DIRECCION INVESTIGACION', 'DIRECION DE INVESTIGACION', 'UNIDAD DE INVESTIGACION']],
        'DG'    => ['Dirección General', 'DIRECCION', null, null, []],
        'SG'    => ['Secretaría General', 'OFICINA', null, null, ['SECRETARIA INSTITUCIONAL']],
        'ORRI'  => ['ORRI', 'OFICINA', null, 'ORRI', ['URRI']],
        'OPP'   => ['OPP', 'OFICINA', null, 'OPP', ['UPP']],
        'CAL'   => ['Oficina de Calidad', 'OFICINA', null, null, ['UNIDAD DE CALIDAD']],
        'IIGMA' => ['Instituto General Marín', 'OFICINA', null, 'IIGMA', ['IIGMA', 'INSTITUTO MARIN']],
        'RS'    => ['Responsabilidad Social', 'OFICINA', null, null, ['RES', 'RESPONSABILIDAD SOLCIAL', 'UNIDAD DE RESPONSABILIDAD SOCIAL']],
        'AJ'    => ['Asesoría Jurídica', 'OFICINA', null, null, ['AJ', 'UNIDAD DE ASESORIA JURIDICA', 'OFICINA DE ASESORIA JURIDICA']],
        'OTIC'  => ['Oficina de Tecnologías de la Información y Comunicación', 'OFICINA', null, 'OTIC', ['OTIC', 'UTIC', 'PC DISPONIBLE']],
        'AULAS' => ['Aulas', 'OFICINA', null, null, []],

        'DA_EVA'  => ['Dpto. Evaluación', 'DEPARTAMENTO', 'DA', null, []],
        'DA_DIP'  => ['Dpto. Diplomados', 'DEPARTAMENTO', 'DA', null, []],
        'DA_MAE'  => ['Dpto. Maestrías', 'DEPARTAMENTO', 'DA', null, []],
        'DA_POS'  => ['Dpto. Postgrados', 'DEPARTAMENTO', 'DA', null, []],
        'DA_AV'   => ['Dpto. Aulas Virtuales (Maestrías)', 'DEPARTAMENTO', 'DA', null, ['AULAS VIRTUALES']],
        'DA_MAT'  => ['División de Matrículas', 'DEPARTAMENTO', 'DA', null, []],
        'DA_SIM'  => ['Dpto. Simulación', 'DEPARTAMENTO', 'DA', null, ['DEPART SIMULACION']],
        'ADM_FIN' => ['Dpto. Finanzas', 'DEPARTAMENTO', 'DADM', null, ['FINANZAS']],
        'ADM_LOG' => ['Dpto. Logística y Abasto', 'DEPARTAMENTO', 'DADM', null, []],
        'ADM_RH'  => ['Dpto. Recursos Humanos', 'DEPARTAMENTO', 'DADM', null, ['RHH', 'RECURSOS HUMANOS', 'DPT DE RRHH']],
        'ADM_SEG' => ['Seguridad', 'OFICINA', 'DADM', null, []],
        'ADM_TOP' => ['Tópico', 'OFICINA', 'DADM', null, []],
        'ADM_COU' => ['Counter', 'OFICINA', 'DADM', null, []],
        'DI_INV'  => ['Dpto. de Investigación', 'DEPARTAMENTO', 'DI', null, []],
        'DI_BIB'  => ['Biblioteca', 'OFICINA', 'DI', null, []],
        'SG_GYR'  => ['Grados y Resoluciones', 'OFICINA', 'SG', null, []],
        'SG_GYT'  => ['Grados y Títulos', 'OFICINA', 'SG', null, []],
        'SG_MP'   => ['Mesa de Partes', 'OFICINA', 'SG', null, []],
        'ORRI_CAP' => ['Captación', 'OFICINA', 'ORRI', null, []],
        'TIC_JEF' => ['Jefatura', 'OFICINA', 'OTIC', null, []],
        'TIC_INF' => ['Infraestructura', 'OFICINA', 'OTIC', null, []],
        'TIC_TEL' => ['Telemática', 'OFICINA', 'OTIC', null, []],
        'TIC_DES' => ['Desarrollo', 'OFICINA', 'OTIC', null, []],
        'TIC_DC'  => ['Data Center', 'OFICINA', 'OTIC', null, []],
        'TIC_ALM' => ['Almacén OTIC', 'OFICINA', 'OTIC', null, ['ALMACEN']],
        'AU_PER'  => ['Aula Perú', 'OFICINA', 'AULAS', null, ['PERU']],
        'AU_MAR'  => ['Salón Marín', 'OFICINA', 'AULAS', null, []],
        'AU_BOL'  => ['Aula Bolognesi', 'OFICINA', 'AULAS', null, ['BOLOGNESI']],
        'AU_QUI'  => ['Aula Quiñones', 'OFICINA', 'AULAS', null, ['QUINONES']],
        'AU_GRA'  => ['Aula Grau', 'OFICINA', 'AULAS', null, ['GRAU']],
        'AU_VAL'  => ['Aula Valer', 'OFICINA', 'AULAS', null, ['VALER']],
        'AU_CAC'  => ['Aula Cáceres', 'OFICINA', 'AULAS', null, ['CACERES']],
    ];

    /** Valores de la columna USUARIO que no son personas. */
    private const NO_PERSONAS = '/^(SIN (USUARIO|ASIGNAR)|ALMACEN.*|SEGURIDAD|SOPORTE.*|AULA.*|SALON.*|GRAU|IMPRESORA|ANTIGUA|S U)$/';

    private const VACIOS = ['', '-', '--', '_', 'S/C', 'S/N', 'SN', 'N/A', 'NA'];

    private Database $db;
    private EquipoService $equipoService;
    private PersonalService $personalService;
    private OficinaService $oficinaService;
    private EquipoModel $equipos;
    private EquipoCodigoModel $codigos;

    /** @var array<string, string> alias normalizado => clave del catálogo */
    private array $alias = [];
    /** @var array<string, int> */
    private array $oficinaIds = [];
    /** @var array<string, ?int> */
    private array $personalIds = [];

    /** @var list<string> */
    public array $oficinasCreadas = [];
    /** @var list<string> */
    public array $personalCreado = [];
    /** @var array<string, int> */
    public array $equiposCreados = [];
    /** @var list<string> */
    public array $omitidas = [];
    /** @var list<string> */
    public array $avisos = [];

    public function __construct(private readonly LectorXlsx $lector, private readonly int $usuarioId)
    {
        $this->db = Database::getInstance();
        $this->equipoService = new EquipoService();
        $this->personalService = new PersonalService();
        $this->oficinaService = new OficinaService();
        $this->equipos = new EquipoModel();
        $this->codigos = new EquipoCodigoModel();

        foreach (self::OFICINAS as $clave => [$nombre, , , , $alias]) {
            foreach ([$nombre, ...$alias] as $texto) {
                $this->alias[self::clave($texto)] = $clave;
            }
        }
    }

    public static function marcaImportacion(): string
    {
        return self::MARCA;
    }

    public function importar(): void
    {
        $this->hojaAsignadas();
        $this->hojaBaja();
        $this->hojaImpresoras();
    }

    // ------------------------------------------------------------------ hojas

    private function hojaAsignadas(): void
    {
        $hoja = 'PCS ASIGNADAS';
        $filas = $this->lector->hoja($hoja);
        $this->exigirCabeceras($hoja, $filas[1] ?? [], ['B' => 'UBICACION', 'F' => 'TIPO', 'L' => 'USUARIO', 'U' => 'HOSTNAME', 'V' => 'NRO SERIE']);

        $direccion = '';
        $ubicacion = '';
        foreach ($filas as $n => $f) {
            $tipo = self::clave($f['F'] ?? '');
            if ($n < 2 || !in_array($tipo, ['PC', 'LAPTOP'], true)) {
                continue;
            }
            $direccion = ($f['A'] ?? '') !== '' ? $f['A'] : $direccion;
            $ubicacion = ($f['B'] ?? '') !== '' ? $f['B'] : $ubicacion;
            $v = static fn (string $col): string => $f[$col] ?? '';

            $oficinaId = $this->oficina($direccion, $ubicacion, $hoja, $n);
            $notas = [];
            $notas[] = $this->nota('Observación', $v('AO'));
            $notas[] = $this->nota('Último mantenimiento', self::fechaExcel($v('H')));
            $notas[] = $this->nota('Vida útil propuesta (meses)', $v('I'));
            $notas[] = $this->nota('Baja sugerida', self::fechaExcel($v('J')));
            $notas[] = $this->nota('Antigüedad', $v('K'));
            $notas[] = $this->nota('N° de informe', $v('P'));
            $notas[] = $this->nota('PECOSA', $v('Q'));
            $notas[] = $this->nota('Disco sólido', trim($v('N') . ($v('O') !== '' ? ' (serie ' . $v('O') . ')' : '')));
            $notas[] = $this->nota('Usuario de Windows', $v('W'));
            $notas[] = $this->nota('Fecha BIOS', self::fechaExcel($v('X')));
            $notas[] = $this->nota('Núcleos/hilos CPU', $v('AA') !== '' ? $v('AA') . '/' . $v('AB') : '');
            $notas[] = $this->nota('Modelo de disco', $v('AC'));
            $notas[] = $this->nota('IP Wi-Fi', self::ip($v('AH')) ?: '');
            $notas[] = $this->nota('Sophos', $v('AJ'));
            $notas[] = $this->nota('Licencia Office 365', $v('AK'));
            $notas[] = $this->nota('AnyDesk ID', $v('AL'));
            $notas[] = $this->nota('Cable de red', $v('AP'));

            $this->crearEquipo($hoja, $n, [
                'tipo'               => $tipo,
                'modelo'             => $v('Y'),
                'nro_serie'          => $v('V'),
                'codigo_interno'     => $v('R'),
                'procesador'         => $v('Z'),
                'ram_gb'             => self::gb($v('AE')),
                'disco_tipo'         => self::tipoDisco($v('AC'), $v('M')),
                'disco_capacidad_gb' => self::gb($v('AD')),
                'sistema_operativo'  => $v('AN'),
                'hostname'           => $v('U'),
                'ip_lan'             => $v('AF'),
                'mac_lan'            => $v('AG'),
                'mac_wifi'           => $v('AI'),
                'oficina_id'         => $oficinaId,
                'personal_id'        => $this->persona($v('L'), $oficinaId),
                'estado_operativo'   => 'OPERATIVO',
                'condicion_fisica'   => self::condicion($v('G')),
            ], [2024 => $v('S'), 2025 => $v('T')], $notas);
        }
    }

    private function hojaBaja(): void
    {
        $hoja = 'EQUIPOS DE BAJA';
        $filas = $this->lector->hoja($hoja);
        $this->exigirCabeceras($hoja, $filas[3] ?? [], ['D' => 'ITEM', 'M' => 'HOSTNAME', 'N' => 'NRO SERIE']);

        foreach ($filas as $n => $f) {
            $item = self::clave($f['D'] ?? '');
            if ($n < 4 || $item === '') {
                continue;
            }
            $v = static fn (string $col): string => $f[$col] ?? '';
            $tipo = str_contains($item, 'LAPTOP') ? 'LAPTOP' : 'PC';
            $oficinaId = $this->oficina($v('B'), $v('C'), $hoja, $n);

            // Algunas filas del Excel tienen las columnas corridas: solo se toman los datos de identificación.
            $desalineada = preg_match('/^\d{1,3}\s*GB$/i', $v('V')) !== 1 || is_numeric($v('T')) || is_numeric($v('P'));

            $notas = [];
            $notas[] = $this->nota('Para baja', $v('F'));
            $notas[] = $this->nota('Informe', $v('G'));
            $notas[] = $this->nota('Oficina de origen', $v('K'));
            $notas[] = $this->nota('Último usuario', $v('L'));
            $notas[] = $this->nota('Disco sólido', $v('H'));
            $datos = [
                'tipo'             => $tipo,
                'nro_serie'        => $v('N'),
                'hostname'         => $v('M'),
                'oficina_id'       => $oficinaId,
                'personal_id'      => null,
                'estado_operativo' => 'INOPERATIVO',
                'condicion_fisica' => 'MALO',
            ];
            if ($desalineada) {
                $datos['modelo'] = '';
                $notas[] = 'Fila con columnas desalineadas en el Excel: complete modelo y hardware manualmente.';
                $this->avisos[] = sprintf('%s fila %d: columnas desalineadas; solo se importaron serie, hostname y códigos. Revise el equipo.', $hoja, $n);
            } else {
                $datos += [
                    'modelo'             => $v('P'),
                    'procesador'         => $v('Q'),
                    'ram_gb'             => self::gb($v('V')),
                    'disco_tipo'         => self::tipoDisco($v('T'), ''),
                    'disco_capacidad_gb' => self::gb($v('U')),
                    'sistema_operativo'  => $v('AA'),
                    'ip_lan'             => $v('W'),
                    'mac_lan'            => $v('X'),
                    'mac_wifi'           => $v('Z'),
                ];
                $notas[] = $this->nota('Usuario de Windows', $v('O'));
                $notas[] = $this->nota('Núcleos/hilos CPU', $v('R') !== '' ? $v('R') . '/' . $v('S') : '');
                $notas[] = $this->nota('Modelo de disco', $v('T'));
                $notas[] = $this->nota('IP Wi-Fi', self::ip($v('Y')) ?: '');
            }

            $this->crearEquipo($hoja, $n, $datos, [2024 => $v('I'), 2025 => $v('J')], $notas);
        }
    }

    private function hojaImpresoras(): void
    {
        $hoja = 'IMPRESORAS';
        $filas = $this->lector->hoja($hoja);
        $this->exigirCabeceras($hoja, $filas[1] ?? [], ['B' => 'DIR', 'I' => 'MODELO', 'J' => 'SERIE']);

        $direccion = '';
        foreach ($filas as $n => $f) {
            $v = static fn (string $col): string => $f[$col] ?? '';
            if ($n < 2 || ($v('H') === '' && $v('J') === '')) {
                continue;
            }
            $direccion = $v('B') !== '' ? $v('B') : $direccion;
            $estado = self::clave($v('E')) === 'INOPERATIVO' ? 'INOPERATIVO' : 'OPERATIVO';
            $marca = self::clave($v('H')) === 'ECOTANK' ? 'EPSON' : mb_strtoupper($v('H'));

            $this->crearEquipo($hoja, $n, [
                'tipo'               => 'IMPRESORA',
                'marca'              => $marca,
                'modelo'             => $v('I'),
                'nro_serie'          => $v('J'),
                'codigo_patrimonial' => $v('K'),
                'oficina_id'         => $this->oficina($direccion, $v('C'), $hoja, $n),
                'personal_id'        => null,
                'estado_operativo'   => $estado,
                'condicion_fisica'   => $estado === 'INOPERATIVO' ? 'MALO' : 'BUENO',
            ], [2024 => $v('M'), 2025 => $v('N')], [
                $this->nota('Uso', $v('F')),
                $this->nota('Mantenimiento 2025', self::fechaExcel($v('L'))),
            ]);
        }
    }

    // ------------------------------------------------------------------ equipos

    /**
     * @param array<string, mixed> $d
     * @param array<int, string> $codigosPorAnio
     * @param list<string> $notas
     */
    private function crearEquipo(string $hoja, int $fila, array $d, array $codigosPorAnio, array $notas): void
    {
        $ref = sprintf('%s fila %d', $hoja, $fila);
        $esImpresora = $d['tipo'] === 'IMPRESORA';

        $d['modelo'] = self::limpio((string) ($d['modelo'] ?? '')) ?? 'SIN MODELO';
        $d['marca'] ??= self::marcaDesdeModelo($d['modelo']);
        foreach (['nro_serie', 'codigo_patrimonial', 'codigo_interno', 'hostname'] as $campo) {
            $d[$campo] = self::limpio((string) ($d[$campo] ?? ''));
            $d[$campo] = $d[$campo] === null ? null : mb_strtoupper($d[$campo]);
        }
        foreach (['procesador' => 120, 'sistema_operativo' => 80] as $campo => $max) {
            $d[$campo] = $esImpresora ? null : self::limpio((string) ($d[$campo] ?? ''));
            $d[$campo] = $d[$campo] === null ? null : mb_substr($d[$campo], 0, $max);
        }

        // Un número de serie repetido es el mismo equipo físico: la fila se omite.
        if ($d['nro_serie'] !== null && ($otro = $this->equipos->otroConValor('nro_serie', $d['nro_serie'], null)) !== null) {
            $this->omitidas[] = sprintf('%s: serie %s ya registrada en el equipo #%d (fila repetida).', $ref, $d['nro_serie'], $otro['id']);

            return;
        }

        // Datos de red: se descartan los inválidos o repetidos (con nota en observaciones).
        $d['ip_lan'] = $this->descartarInvalido($d, 'ip_lan', 'IP LAN', self::ip((string) ($d['ip_lan'] ?? '')), $notas);
        foreach (['mac_lan' => 'MAC LAN', 'mac_wifi' => 'MAC Wi-Fi'] as $campo => $etiqueta) {
            $d[$campo] = $this->descartarInvalido($d, $campo, $etiqueta, self::mac((string) ($d[$campo] ?? '')), $notas);
        }
        if ($d['mac_wifi'] !== null && $d['mac_wifi'] === $d['mac_lan']) {
            $notas[] = 'MAC Wi-Fi igual a la MAC LAN: se omitió.';
            $d['mac_wifi'] = null;
        }
        if ($d['hostname'] !== null && preg_match('/^[A-Z0-9](?:[A-Z0-9\-]{0,61}[A-Z0-9])?$/', $d['hostname']) !== 1) {
            $notas[] = 'Hostname no válido en el Excel: ' . $d['hostname'];
            $d['hostname'] = null;
        }
        foreach (['codigo_patrimonial' => 'código patrimonial', 'codigo_interno' => 'código interno', 'hostname' => 'hostname', 'ip_lan' => 'IP LAN', 'mac_lan' => 'MAC LAN', 'mac_wifi' => 'MAC Wi-Fi'] as $campo => $etiqueta) {
            if ($d[$campo] !== null && ($otro = $this->equipos->otroConValor($campo, (string) $d[$campo], null)) !== null) {
                $notas[] = sprintf('%s %s repetido en el Excel (ya lo usa el equipo #%d): no se guardó.', ucfirst($etiqueta), $d[$campo], $otro['id']);
                $this->avisos[] = sprintf('%s: %s %s repetido (equipo #%d); se dejó vacío.', $ref, $etiqueta, $d[$campo], $otro['id']);
                $d[$campo] = null;
            }
        }

        $anios = [];
        $valores = [];
        foreach ($codigosPorAnio as $anio => $codigo) {
            $codigo = self::limpio($codigo);
            if ($codigo === null) {
                continue;
            }
            $codigo = mb_strtoupper($codigo);
            if (($otro = $this->codigos->equipoConCodigo($anio, $codigo, null)) !== null) {
                $notas[] = sprintf('Código %d %s repetido (ya lo usa el equipo #%d): no se guardó.', $anio, $codigo, $otro);
                $this->avisos[] = sprintf('%s: código %d %s repetido (equipo #%d); se omitió.', $ref, $anio, $codigo, $otro);
                continue;
            }
            $anios[] = (string) $anio;
            $valores[] = $codigo;
        }

        $observaciones = implode("\n", array_filter([sprintf('%s: %s]', self::MARCA, $ref), ...$notas], static fn (string $t): bool => $t !== ''));

        $entrada = [
            'tipo'                    => $d['tipo'],
            'marca'                   => mb_substr($d['marca'], 0, 60),
            'modelo'                  => mb_substr($d['modelo'], 0, 100),
            'nro_serie'               => $d['nro_serie'] ?? '',
            'codigo_patrimonial'      => $d['codigo_patrimonial'] ?? '',
            'codigo_interno'          => $d['codigo_interno'] ?? '',
            'procesador'              => $d['procesador'] ?? '',
            'ram_gb'                  => $esImpresora ? '' : (string) ($d['ram_gb'] ?? ''),
            'disco_tipo'              => $esImpresora ? '' : (string) ($d['disco_tipo'] ?? ''),
            'disco_capacidad_gb'      => $esImpresora ? '' : (string) ($d['disco_capacidad_gb'] ?? ''),
            'sistema_operativo'       => $d['sistema_operativo'] ?? '',
            'hostname'                => $d['hostname'] ?? '',
            'ip_lan'                  => $d['ip_lan'] ?? '',
            'mac_lan'                 => $d['mac_lan'] ?? '',
            'mac_wifi'                => $d['mac_wifi'] ?? '',
            'oficina_id'              => (string) $d['oficina_id'],
            'personal_id'             => $d['personal_id'] === null ? '' : (string) $d['personal_id'],
            'estado_operativo'        => $d['estado_operativo'],
            'condicion_fisica'        => $d['condicion_fisica'],
            'fecha_adquisicion'       => '',
            'garantia_hasta'          => '',
            'vida_util_meses'         => '',
            'periodicidad_mant_meses' => '',
            'valor_adquisicion'       => '',
            'orden_compra'            => '',
            'proveedor'               => '',
            'observaciones'           => mb_substr($observaciones, 0, 2000),
            'codigos_anio'            => $anios,
            'codigos_codigo'          => $valores,
        ];

        try {
            $this->equipoService->crear($entrada, $this->usuarioId);
            $this->equiposCreados[$hoja] = ($this->equiposCreados[$hoja] ?? 0) + 1;
        } catch (ValidationException $e) {
            $this->omitidas[] = sprintf('%s: %s', $ref, implode(' | ', $e->getErrores()));
        }
        $this->verificarTransaccion($ref);
    }

    /**
     * @param array<string, mixed> $d
     * @param list<string> $notas
     */
    private function descartarInvalido(array $d, string $campo, string $etiqueta, string|false|null $valor, array &$notas): ?string
    {
        if ($valor === false) {
            $notas[] = sprintf('%s no válida en el Excel: %s', $etiqueta, trim((string) $d[$campo]));

            return null;
        }

        return $valor;
    }

    // ------------------------------------------------------------------ oficinas y personal

    private function oficina(string $padre, string $hijo, string $hoja, int $fila): int
    {
        $clavePadre = $this->alias[self::clave($padre)] ?? null;
        $claveHijo = $this->alias[self::clave($hijo)] ?? null;

        if ($claveHijo !== null) {
            return $this->asegurarCatalogo($claveHijo);
        }
        $padreId = $clavePadre !== null ? $this->asegurarCatalogo($clavePadre) : null;
        if (self::limpio($hijo) === null) {
            if ($padreId !== null) {
                return $padreId;
            }
            if (self::limpio($padre) === null) {
                $this->avisos[] = sprintf('%s fila %d: sin ubicación; se asignó al Almacén OTIC.', $hoja, $fila);

                return $this->asegurarCatalogo('TIC_ALM');
            }

            return $this->asegurar(self::titulo($padre), 'OFICINA', null, null);
        }
        if ($padreId === null && self::limpio($padre) !== null) {
            $padreId = $this->asegurar(self::titulo($padre), 'OFICINA', null, null);
        }

        return $this->asegurar(self::titulo($hijo), 'OFICINA', $padreId, null);
    }

    private function asegurarCatalogo(string $clave): int
    {
        [$nombre, $tipo, $padre, $siglas] = self::OFICINAS[$clave];
        $padreId = $padre === null ? null : $this->asegurarCatalogo($padre);

        return $this->asegurar($nombre, $tipo, $padreId, $siglas);
    }

    private function asegurar(string $nombre, string $tipo, ?int $padreId, ?string $siglas): int
    {
        $cache = self::clave($nombre) . '|' . ($padreId ?? 0);
        if (isset($this->oficinaIds[$cache])) {
            return $this->oficinaIds[$cache];
        }

        // La collation utf8mb4_unicode_ci ignora mayúsculas y tildes: reutiliza oficinas ya creadas a mano.
        $existente = $this->db->run(
            'SELECT id FROM oficinas WHERE nombre = :nombre AND padre_id <=> :padre LIMIT 1',
            ['nombre' => $nombre, 'padre' => $padreId]
        )->fetchColumn();
        if ($existente === false && $siglas !== null) {
            $existente = $this->db->run(
                'SELECT id FROM oficinas WHERE siglas = :siglas AND padre_id <=> :padre LIMIT 1',
                ['siglas' => $siglas, 'padre' => $padreId]
            )->fetchColumn();
        }

        if ($existente !== false) {
            $id = (int) $existente;
        } else {
            $id = $this->oficinaService->crear([
                'nombre'   => $nombre,
                'tipo'     => $tipo,
                'padre_id' => $padreId === null ? '' : (string) $padreId,
                'siglas'   => $siglas ?? '',
            ], $this->usuarioId);
            $this->oficinasCreadas[] = $nombre . ($padreId !== null ? ' (dentro de #' . $padreId . ')' : '');
            $this->verificarTransaccion('oficina ' . $nombre);
        }

        return $this->oficinaIds[$cache] = $id;
    }

    private function persona(string $texto, int $oficinaId): ?int
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', $texto));
        $clave = self::clave($nombre);
        if ($clave === '' || preg_match(self::NO_PERSONAS, $clave) === 1 || str_starts_with($nombre, '(')) {
            return null;
        }
        if (array_key_exists($clave, $this->personalIds)) {
            return $this->personalIds[$clave];
        }

        $existente = $this->db->run(
            "SELECT id FROM personal WHERE CONCAT(nombres, ' ', apellidos) = :nombre LIMIT 1",
            ['nombre' => $nombre]
        )->fetchColumn();
        if ($existente !== false) {
            return $this->personalIds[$clave] = (int) $existente;
        }

        // Sin columnas separadas: 2 palabras = 1+1, 3 = 1+2, 4 o más = el resto + 2 apellidos.
        $partes = explode(' ', self::titulo($nombre));
        $cantidad = count($partes);
        $nApellidos = match (true) {
            $cantidad === 1 => 0,
            $cantidad === 2 => 1,
            default         => 2,
        };
        $nombres = implode(' ', array_slice($partes, 0, $cantidad - $nApellidos));
        $apellidos = $nApellidos === 0 ? '-' : implode(' ', array_slice($partes, $cantidad - $nApellidos));

        try {
            $id = $this->personalService->crear([
                'nombres'    => mb_substr($nombres, 0, 100),
                'apellidos'  => mb_substr($apellidos, 0, 100),
                'oficina_id' => (string) $oficinaId,
            ], $this->usuarioId);
        } catch (ValidationException $e) {
            $this->avisos[] = sprintf('Personal "%s" no se pudo crear: %s', $nombre, implode(' | ', $e->getErrores()));

            return $this->personalIds[$clave] = null;
        }
        $this->verificarTransaccion('personal ' . $nombre);
        $this->personalCreado[] = $nombres . ' / ' . $apellidos;
        if ($nApellidos === 0) {
            $this->avisos[] = sprintf('Personal "%s": solo tiene un nombre en el Excel; complete sus apellidos.', $nombre);
        }

        return $this->personalIds[$clave] = $id;
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * @param array<string, string> $cabecera
     * @param array<string, string> $esperadas columna => texto normalizado esperado
     */
    private function exigirCabeceras(string $hoja, array $cabecera, array $esperadas): void
    {
        foreach ($esperadas as $col => $texto) {
            if (self::clave($cabecera[$col] ?? '') !== $texto) {
                throw new RuntimeException(sprintf(
                    'La hoja "%s" cambió de formato: se esperaba "%s" en la columna %s y hay "%s".',
                    $hoja,
                    $texto,
                    $col,
                    $cabecera[$col] ?? ''
                ));
            }
        }
    }

    /** Si un servicio revirtió la transacción por un error de BD, se aborta todo (no se admite importación parcial). */
    private function verificarTransaccion(string $ref): void
    {
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('Error de base de datos al procesar ' . $ref . '. Revise storage/logs/app.log.');
        }
    }

    private function nota(string $etiqueta, string $valor): string
    {
        $valor = trim($valor);

        return $valor === '' ? '' : $etiqueta . ': ' . $valor;
    }

    private static function clave(string $texto): string
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $t = (string) preg_replace('/[^A-Z0-9]+/', ' ', $t);

        return trim($t);
    }

    private static function limpio(string $valor): ?string
    {
        $valor = trim((string) preg_replace('/\s+/u', ' ', $valor));

        return in_array(mb_strtoupper($valor), self::VACIOS, true) ? null : $valor;
    }

    private static function titulo(string $texto): string
    {
        $t = mb_convert_case(mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $texto))), MB_CASE_TITLE);

        return (string) preg_replace_callback(
            '/(?<=\s)(De|Del|La|Las|Los|Y|E)(?=\s)/u',
            static fn (array $m): string => mb_strtolower($m[1]),
            $t
        );
    }

    private static function fechaExcel(string $valor): string
    {
        if (!ctype_digit($valor) || (int) $valor < 20000 || (int) $valor > 80000) {
            return $valor;
        }

        return (new DateTimeImmutable('1899-12-30'))->modify('+' . (int) $valor . ' days')->format('d/m/Y');
    }

    /** "894.3GB" / "931,5GB" / "238 GB" -> 894 / 932 / 238. */
    private static function gb(string $valor): ?int
    {
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*GB$/i', trim($valor), $m) !== 1) {
            return null;
        }
        $gb = (int) round((float) str_replace(',', '.', $m[1]));

        return $gb > 0 ? $gb : null;
    }

    private static function tipoDisco(string $modelo, string $tieneSolido): ?string
    {
        $m = mb_strtoupper($modelo);

        return match (true) {
            preg_match('/SNV|NVME|TM8FP/', $m) === 1                  => 'NVME',
            preg_match('/SA400|SSD|KINGSTON/', $m) === 1              => 'SSD',
            preg_match('/^(ST\d|WDC|HGST|TOSHIBA|HDD)/', $m) === 1    => 'HDD',
            self::clave($tieneSolido) === 'SI'                        => 'SSD',
            self::clave($tieneSolido) === 'NO'                        => 'HDD',
            default                                                   => null,
        };
    }

    private static function condicion(string $valor): string
    {
        $c = self::clave($valor);

        return in_array($c, ['BUENO', 'REGULAR', 'MALO'], true) ? $c : 'REGULAR';
    }

    private static function marcaDesdeModelo(string $modelo): string
    {
        $m = mb_strtoupper($modelo);

        return match (true) {
            preg_match('/VOSTRO|OPTIPLEX|LATITUDE|INSPIRON|DELL/', $m) === 1  => 'DELL',
            preg_match('/^HP|PRODESK|ELITEDESK|PROBOOK|ELITEBOOK/', $m) === 1 => 'HP',
            str_contains($m, 'LENOVO') || str_contains($m, 'IDEAPAD')         => 'LENOVO',
            str_contains($m, 'ASUS')                                           => 'ASUS',
            default                                                            => 'SIN MARCA',
        };
    }

    private static function ip(string $valor): string|false|null
    {
        return EquipoService::normalizarIp(self::limpio($valor));
    }

    private static function mac(string $valor): string|false|null
    {
        return EquipoService::normalizarMac(self::limpio($valor));
    }
}

// ---------------------------------------------------------------------- ejecución

$argumentos = array_slice($argv, 1);
$archivo = null;
$ejecutar = false;
$forzar = false;
$login = 'admin';
foreach ($argumentos as $arg) {
    if ($arg === '--ejecutar') {
        $ejecutar = true;
    } elseif ($arg === '--forzar') {
        $forzar = true;
    } elseif (str_starts_with($arg, '--usuario=')) {
        $login = substr($arg, 10);
    } else {
        $archivo = $arg;
    }
}

if ($archivo === null || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php database/importar_excel.php \"C:\\ruta\\INVENTARIO 2026.xlsx\" [--ejecutar] [--usuario=admin] [--forzar]\n");
    exit(1);
}

$db = Database::getInstance();
$inicio = microtime(true);

try {
    $usuarioId = $db->run('SELECT id FROM usuarios_sistema WHERE usuario = :u AND activo = 1', ['u' => $login])->fetchColumn();
    if ($usuarioId === false) {
        throw new RuntimeException('No existe el usuario del sistema activo "' . $login . '" (use --usuario=...).');
    }

    $previos = (int) $db->run('SELECT COUNT(*) FROM equipos WHERE observaciones LIKE :m', ['m' => ImportadorInventario::marcaImportacion() . '%'])->fetchColumn();
    if ($previos > 0 && !$forzar) {
        throw new RuntimeException(sprintf('Ya hay %d equipos importados desde Excel. Para importar de nuevo use --forzar (las series repetidas se omiten).', $previos));
    }

    $importador = new ImportadorInventario(new LectorXlsx($archivo), (int) $usuarioId);

    $db->beginTransaction();
    try {
        $importador->importar();
        if ($ejecutar) {
            $db->commit();
        } else {
            $db->rollBack();
        }
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
} catch (Throwable $e) {
    $detalle = $e instanceof DatabaseException && $e->getPrevious() !== null ? ' (' . $e->getPrevious()->getMessage() . ')' : '';
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . $detalle . "\nNo se guardó ningún dato.\n");
    exit(1);
}

$lineas = [];
$lineas[] = $ejecutar ? '=== IMPORTACIÓN REALIZADA ===' : '=== SIMULACIÓN (no se guardó nada; use --ejecutar para guardar) ===';
$lineas[] = 'Archivo: ' . $archivo;
$lineas[] = '';
$lineas[] = 'Equipos creados:';
foreach ($importador->equiposCreados as $hoja => $total) {
    $lineas[] = sprintf('  %-18s %d', $hoja, $total);
}
$lineas[] = sprintf('  %-18s %d', 'TOTAL', array_sum($importador->equiposCreados));
$lineas[] = '';
$lineas[] = 'Oficinas creadas (' . count($importador->oficinasCreadas) . '):';
foreach ($importador->oficinasCreadas as $o) {
    $lineas[] = '  - ' . $o;
}
$lineas[] = '';
$lineas[] = 'Personal creado (' . count($importador->personalCreado) . ', formato nombres / apellidos):';
foreach ($importador->personalCreado as $p) {
    $lineas[] = '  - ' . $p;
}
$lineas[] = '';
$lineas[] = 'Filas omitidas (' . count($importador->omitidas) . '):';
foreach ($importador->omitidas as $o) {
    $lineas[] = '  - ' . $o;
}
$lineas[] = '';
$lineas[] = 'Avisos (' . count($importador->avisos) . '):';
foreach ($importador->avisos as $a) {
    $lineas[] = '  - ' . $a;
}
$lineas[] = '';
$lineas[] = sprintf('Tiempo: %.1f s', microtime(true) - $inicio);

$reporte = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $reporte;

$rutaReporte = $raiz . '/storage/logs/importacion_' . date('Ymd_His') . ($ejecutar ? '' : '_simulacion') . '.txt';
file_put_contents($rutaReporte, $reporte);
echo 'Reporte guardado en: ' . $rutaReporte . PHP_EOL;
