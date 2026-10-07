<?php

declare(strict_types=1);

/*
 * Genera un archivo .sql con los equipos de "INVENTARIO 2026.xlsx" para importarlo desde phpMyAdmin
 * (hosting sin acceso a PHP CLI contra la base). No se conecta a ninguna base de datos.
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\xampp\php\php.exe database\generar_sql_equipos.php "C:\ruta\INVENTARIO 2026.xlsx" [salida.sql] [--usuario=admin]
 *
 * Aplica las mismas reglas que importar_excel.php (hojas PCS ASIGNADAS, EQUIPOS DE BAJA e IMPRESORAS,
 * limpieza de datos, series repetidas omitidas, IP/MAC/hostname inválidos o repetidos anotados en
 * observaciones). Las oficinas y el personal se buscan por nombre en la base destino y se crean solo si
 * no existen. Por cada equipo se insertan también sus códigos anuales y el registro ALTA del historial.
 * Todo va en una transacción: si una sentencia falla, phpMyAdmin se detiene y no se confirma nada.
 */

use App\Services\EquipoService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';
require __DIR__ . '/LectorXlsx.php';

final class GeneradorSqlEquipos
{
    private const MARCA = '[Importado de Excel';

    /** Mismo catálogo que importar_excel.php: clave => [nombre, tipo, clave del padre, siglas, alias]. */
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

    private const NO_PERSONAS = '/^(SIN (USUARIO|ASIGNAR)|ALMACEN.*|SEGURIDAD|SOPORTE.*|AULA.*|SALON.*|GRAU|IMPRESORA|ANTIGUA|S U)$/';

    private const VACIOS = ['', '-', '--', '_', 'S/C', 'S/N', 'SN', 'N/A', 'NA'];

    /** @var array<string, string> */
    private array $alias = [];
    /** @var array<string, string> clave de caché => variable SQL de la oficina */
    private array $oficinaVars = [];
    /** @var array<string, ?string> clave de persona => variable SQL (null = no es persona) */
    private array $personalVars = [];
    /** @var array<string, array<string, int>> campo => valor => n° de equipo (duplicados dentro del Excel) */
    private array $usados = [];

    /** @var list<string> */
    private array $sql = [];
    private int $nOficinas = 0;
    private int $nPersonal = 0;
    private int $nEquipos = 0;

    /** @var array<string, int> */
    public array $equiposPorHoja = [];
    /** @var list<string> */
    public array $omitidas = [];
    /** @var list<string> */
    public array $avisos = [];

    public function __construct(private readonly LectorXlsx $lector)
    {
        foreach (self::OFICINAS as $clave => [$nombre, , , , $alias]) {
            foreach ([$nombre, ...$alias] as $texto) {
                $this->alias[self::clave($texto)] = $clave;
            }
        }
    }

    public function generar(string $archivoExcel, string $usuario): string
    {
        $this->hojaAsignadas();
        $this->hojaBaja();
        $this->hojaImpresoras();

        $cabecera = [
            '-- =====================================================================',
            '-- SIGPAT-OTIC - Importación de equipos desde Excel',
            '-- Origen : ' . basename($archivoExcel),
            '-- Fecha  : ' . date('Y-m-d H:i:s'),
            '-- Equipos: ' . $this->nEquipos,
            '--',
            '-- Importar en phpMyAdmin: seleccione la base de datos > pestaña Importar.',
            '-- Requisitos: la tabla equipos debe estar vacía (o sin estas series) y',
            '-- debe existir el usuario del sistema "' . $usuario . '" (autor de los registros).',
            '-- Todo se ejecuta en una transacción: si algo falla no se guarda nada.',
            '-- =====================================================================',
            '',
            'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;',
            "SET time_zone = '-05:00';",
            'START TRANSACTION;',
            '',
            'SET @usr = (SELECT id FROM usuarios_sistema WHERE usuario = ' . self::q($usuario) . ' AND activo = 1 LIMIT 1);',
            "SET @vida = COALESCE((SELECT CAST(valor AS UNSIGNED) FROM parametros WHERE clave = 'vida_util_meses_default'), 48);",
            '',
        ];
        $pie = [
            '',
            'COMMIT;',
            '',
            "SELECT COUNT(*) AS equipos_importados FROM equipos WHERE observaciones LIKE '" . self::MARCA . "%';",
            '',
        ];

        return implode("\n", [...$cabecera, ...$this->sql, ...$pie]);
    }

    // ------------------------------------------------------------------ hojas (idénticas a importar_excel.php)

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

            $oficina = $this->oficina($direccion, $ubicacion, $hoja, $n);
            $notas = [
                $this->nota('Observación', $v('AO')),
                $this->nota('Último mantenimiento', self::fechaExcel($v('H'))),
                $this->nota('Vida útil propuesta (meses)', $v('I')),
                $this->nota('Baja sugerida', self::fechaExcel($v('J'))),
                $this->nota('Antigüedad', $v('K')),
                $this->nota('N° de informe', $v('P')),
                $this->nota('PECOSA', $v('Q')),
                $this->nota('Disco sólido', trim($v('N') . ($v('O') !== '' ? ' (serie ' . $v('O') . ')' : ''))),
                $this->nota('Usuario de Windows', $v('W')),
                $this->nota('Fecha BIOS', self::fechaExcel($v('X'))),
                $this->nota('Núcleos/hilos CPU', $v('AA') !== '' ? $v('AA') . '/' . $v('AB') : ''),
                $this->nota('Modelo de disco', $v('AC')),
                $this->nota('IP Wi-Fi', self::ip($v('AH')) ?: ''),
                $this->nota('Sophos', $v('AJ')),
                $this->nota('Licencia Office 365', $v('AK')),
                $this->nota('AnyDesk ID', $v('AL')),
                $this->nota('Cable de red', $v('AP')),
            ];

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
                'oficina'            => $oficina,
                'personal'           => $this->persona($v('L'), $oficina),
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
            $oficina = $this->oficina($v('B'), $v('C'), $hoja, $n);

            $desalineada = preg_match('/^\d{1,3}\s*GB$/i', $v('V')) !== 1 || is_numeric($v('T')) || is_numeric($v('P'));

            $notas = [
                $this->nota('Para baja', $v('F')),
                $this->nota('Informe', $v('G')),
                $this->nota('Oficina de origen', $v('K')),
                $this->nota('Último usuario', $v('L')),
                $this->nota('Disco sólido', $v('H')),
            ];
            $datos = [
                'tipo'             => $tipo,
                'nro_serie'        => $v('N'),
                'hostname'         => $v('M'),
                'oficina'          => $oficina,
                'personal'         => null,
                'estado_operativo' => 'INOPERATIVO',
                'condicion_fisica' => 'MALO',
            ];
            if ($desalineada) {
                $datos['modelo'] = '';
                $notas[] = 'Fila con columnas desalineadas en el Excel: complete modelo y hardware manualmente.';
                $this->avisos[] = sprintf('%s fila %d: columnas desalineadas; solo se importaron serie, hostname y códigos.', $hoja, $n);
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
                'oficina'            => $this->oficina($direccion, $v('C'), $hoja, $n),
                'personal'           => null,
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
        $d['marca'] = self::limpio((string) $d['marca']) ?? 'SIN MARCA';
        foreach (['nro_serie', 'codigo_patrimonial', 'codigo_interno', 'hostname'] as $campo) {
            $d[$campo] = self::limpio((string) ($d[$campo] ?? ''));
            $d[$campo] = $d[$campo] === null ? null : mb_strtoupper($d[$campo]);
        }
        foreach (['procesador' => 120, 'sistema_operativo' => 80] as $campo => $max) {
            $d[$campo] = $esImpresora ? null : self::limpio((string) ($d[$campo] ?? ''));
            $d[$campo] = $d[$campo] === null ? null : mb_substr($d[$campo], 0, $max);
        }
        foreach (['nro_serie' => 80, 'codigo_patrimonial' => 30, 'codigo_interno' => 30] as $campo => $max) {
            if ($d[$campo] !== null && mb_strlen($d[$campo]) > $max) {
                $notas[] = sprintf('%s demasiado largo en el Excel: %s', $campo, $d[$campo]);
                $d[$campo] = null;
            }
        }

        $numero = $this->nEquipos + 1;
        if ($d['nro_serie'] !== null && isset($this->usados['nro_serie'][$d['nro_serie']])) {
            $this->omitidas[] = sprintf('%s: serie %s repetida (ya está en el equipo n° %d del archivo).', $ref, $d['nro_serie'], $this->usados['nro_serie'][$d['nro_serie']]);

            return;
        }

        $d['ip_lan'] = self::descartarInvalido($d, 'ip_lan', 'IP LAN', self::ip((string) ($d['ip_lan'] ?? '')), $notas);
        foreach (['mac_lan' => 'MAC LAN', 'mac_wifi' => 'MAC Wi-Fi'] as $campo => $etiqueta) {
            $d[$campo] = self::descartarInvalido($d, $campo, $etiqueta, self::mac((string) ($d[$campo] ?? '')), $notas);
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
            if ($d[$campo] !== null && isset($this->usados[$campo][$d[$campo]])) {
                $notas[] = sprintf('%s %s repetido en el Excel: no se guardó.', ucfirst($etiqueta), $d[$campo]);
                $this->avisos[] = sprintf('%s: %s %s repetido; se dejó vacío.', $ref, $etiqueta, $d[$campo]);
                $d[$campo] = null;
            }
        }

        $codigos = [];
        foreach ($codigosPorAnio as $anio => $codigo) {
            $codigo = self::limpio($codigo);
            if ($codigo === null) {
                continue;
            }
            $codigo = mb_strtoupper($codigo);
            if (mb_strlen($codigo) > 40 || isset($this->usados['codigo' . $anio][$codigo])) {
                $notas[] = sprintf('Código %d %s repetido o no válido: no se guardó.', $anio, $codigo);
                $this->avisos[] = sprintf('%s: código %d %s repetido o no válido; se omitió.', $ref, $anio, $codigo);
                continue;
            }
            $codigos[$anio] = $codigo;
        }

        // Registrar valores únicos usados por este equipo.
        foreach (['nro_serie', 'codigo_patrimonial', 'codigo_interno', 'hostname', 'ip_lan', 'mac_lan', 'mac_wifi'] as $campo) {
            if ($d[$campo] !== null) {
                $this->usados[$campo][$d[$campo]] = $numero;
            }
        }
        foreach ($codigos as $anio => $codigo) {
            $this->usados['codigo' . $anio][$codigo] = $numero;
        }

        $observaciones = implode("\n", array_filter([sprintf('%s: %s]', self::MARCA, $ref), ...$notas], static fn (string $t): bool => $t !== ''));
        $ram = $esImpresora ? null : self::rango($d['ram_gb'] ?? null, 1, 1024);
        $capacidad = $esImpresora ? null : self::rango($d['disco_capacidad_gb'] ?? null, 1, 100000);

        $valores = [
            self::q($d['tipo']),
            self::q(mb_substr($d['marca'], 0, 60)),
            self::q(mb_substr($d['modelo'], 0, 100)),
            self::q($d['nro_serie']),
            self::q($d['codigo_patrimonial']),
            self::q($d['codigo_interno']),
            self::q($d['procesador']),
            $ram === null ? 'NULL' : (string) $ram,
            self::q($esImpresora ? null : ($d['disco_tipo'] ?? null)),
            $capacidad === null ? 'NULL' : (string) $capacidad,
            self::q($d['sistema_operativo']),
            self::q($d['hostname']),
            self::q($d['mac_lan']),
            self::q($d['ip_lan']),
            self::q($d['mac_wifi']),
            $d['oficina'],
            $d['personal'] ?? 'NULL',
            self::q($d['estado_operativo']),
            self::q($d['condicion_fisica']),
            '@vida',
            self::q(mb_substr($observaciones, 0, 2000)),
            '@usr',
            '@usr',
        ];

        $this->sql[] = sprintf('-- Equipo %d: %s', $numero, $ref);
        $this->sql[] = 'INSERT INTO equipos (tipo, marca, modelo, nro_serie, codigo_patrimonial, codigo_interno, procesador, ram_gb, disco_tipo, disco_capacidad_gb, sistema_operativo, hostname, mac_lan, ip_lan, mac_wifi, oficina_id, personal_id, estado_operativo, condicion_fisica, vida_util_meses, observaciones, created_by, updated_by)';
        $this->sql[] = '  VALUES (' . implode(', ', $valores) . ');';
        $this->sql[] = 'SET @eq = LAST_INSERT_ID();';
        if ($codigos !== []) {
            $filas = [];
            foreach ($codigos as $anio => $codigo) {
                $filas[] = sprintf('(@eq, %d, %s)', $anio, self::q($codigo));
            }
            $this->sql[] = 'INSERT INTO equipo_codigos (equipo_id, anio, codigo) VALUES ' . implode(', ', $filas) . ';';
        }
        $this->sql[] = sprintf(
            "INSERT INTO historial_asignaciones (equipo_id, oficina_id, personal_id, motivo, observacion, usuario_id) VALUES (@eq, %s, %s, 'ALTA', 'Alta del equipo en el inventario', @usr);",
            $d['oficina'],
            $d['personal'] ?? 'NULL'
        );
        $this->sql[] = '';

        $this->nEquipos = $numero;
        $this->equiposPorHoja[$hoja] = ($this->equiposPorHoja[$hoja] ?? 0) + 1;
    }

    /**
     * @param array<string, mixed> $d
     * @param list<string> $notas
     */
    private static function descartarInvalido(array $d, string $campo, string $etiqueta, string|false|null $valor, array &$notas): ?string
    {
        if ($valor === false) {
            $notas[] = sprintf('%s no válida en el Excel: %s', $etiqueta, trim((string) $d[$campo]));

            return null;
        }

        return $valor;
    }

    // ------------------------------------------------------------------ oficinas y personal (se resuelven en la base destino)

    /** @return string variable SQL con el id de la oficina */
    private function oficina(string $padre, string $hijo, string $hoja, int $fila): string
    {
        $clavePadre = $this->alias[self::clave($padre)] ?? null;
        $claveHijo = $this->alias[self::clave($hijo)] ?? null;

        if ($claveHijo !== null) {
            return $this->asegurarCatalogo($claveHijo);
        }
        $padreVar = $clavePadre !== null ? $this->asegurarCatalogo($clavePadre) : null;
        if (self::limpio($hijo) === null) {
            if ($padreVar !== null) {
                return $padreVar;
            }
            if (self::limpio($padre) === null) {
                $this->avisos[] = sprintf('%s fila %d: sin ubicación; se asignó al Almacén OTIC.', $hoja, $fila);

                return $this->asegurarCatalogo('TIC_ALM');
            }

            return $this->asegurar(self::titulo($padre), 'OFICINA', null, null);
        }
        if ($padreVar === null && self::limpio($padre) !== null) {
            $padreVar = $this->asegurar(self::titulo($padre), 'OFICINA', null, null);
        }

        return $this->asegurar(self::titulo($hijo), 'OFICINA', $padreVar, null);
    }

    private function asegurarCatalogo(string $clave): string
    {
        [$nombre, $tipo, $padre, $siglas] = self::OFICINAS[$clave];
        $padreVar = $padre === null ? null : $this->asegurarCatalogo($padre);

        return $this->asegurar($nombre, $tipo, $padreVar, $siglas);
    }

    private function asegurar(string $nombre, string $tipo, ?string $padreVar, ?string $siglas): string
    {
        $cache = self::clave($nombre) . '|' . ($padreVar ?? '');
        if (isset($this->oficinaVars[$cache])) {
            return $this->oficinaVars[$cache];
        }

        $var = '@of' . ++$this->nOficinas;
        $padre = $padreVar ?? 'NULL';
        $n = self::q($nombre);
        $this->sql[] = '-- Oficina: ' . $nombre;
        $this->sql[] = "SET $var = (SELECT id FROM oficinas WHERE nombre = $n AND padre_id <=> $padre LIMIT 1);";
        if ($siglas !== null) {
            $s = self::q($siglas);
            $this->sql[] = "SET $var = COALESCE($var, (SELECT id FROM oficinas WHERE siglas = $s AND padre_id <=> $padre LIMIT 1));";
        }
        $this->sql[] = sprintf(
            'INSERT INTO oficinas (padre_id, nombre, siglas, tipo) SELECT %s, %s, %s, %s FROM DUAL WHERE %s IS NULL;',
            $padre,
            $n,
            self::q($siglas),
            self::q($tipo),
            $var
        );
        $this->sql[] = "SET $var = COALESCE($var, (SELECT id FROM oficinas WHERE nombre = $n AND padre_id <=> $padre LIMIT 1));";
        $this->sql[] = '';

        return $this->oficinaVars[$cache] = $var;
    }

    /** @return ?string variable SQL con el id de la persona, o null si el texto no es una persona */
    private function persona(string $texto, string $oficinaVar): ?string
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', $texto));
        $clave = self::clave($nombre);
        if ($clave === '' || preg_match(self::NO_PERSONAS, $clave) === 1 || str_starts_with($nombre, '(')) {
            return null;
        }
        if (array_key_exists($clave, $this->personalVars)) {
            return $this->personalVars[$clave];
        }

        $partes = explode(' ', self::titulo($nombre));
        $cantidad = count($partes);
        $nApellidos = match (true) {
            $cantidad === 1 => 0,
            $cantidad === 2 => 1,
            default         => 2,
        };
        $nombres = mb_substr(implode(' ', array_slice($partes, 0, $cantidad - $nApellidos)), 0, 100);
        $apellidos = $nApellidos === 0 ? '-' : mb_substr(implode(' ', array_slice($partes, $cantidad - $nApellidos)), 0, 100);
        if ($nApellidos === 0) {
            $this->avisos[] = sprintf('Personal "%s": solo tiene un nombre en el Excel; complete sus apellidos.', $nombre);
        }

        $var = '@pe' . ++$this->nPersonal;
        $buscar = sprintf(
            "(SELECT id FROM personal WHERE CONCAT(nombres, ' ', apellidos) = %s OR (nombres = %s AND apellidos = %s) LIMIT 1)",
            self::q($nombre),
            self::q($nombres),
            self::q($apellidos)
        );
        $this->sql[] = '-- Personal: ' . $nombre;
        $this->sql[] = "SET $var = $buscar;";
        $this->sql[] = sprintf(
            'INSERT INTO personal (nombres, apellidos, oficina_id) SELECT %s, %s, %s FROM DUAL WHERE %s IS NULL;',
            self::q($nombres),
            self::q($apellidos),
            $oficinaVar,
            $var
        );
        $this->sql[] = sprintf(
            "SET $var = COALESCE($var, (SELECT id FROM personal WHERE nombres = %s AND apellidos = %s ORDER BY id DESC LIMIT 1));",
            self::q($nombres),
            self::q($apellidos)
        );
        $this->sql[] = '';

        return $this->personalVars[$clave] = $var;
    }

    // ------------------------------------------------------------------ utilidades

    /**
     * @param array<string, string> $cabecera
     * @param array<string, string> $esperadas
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

    private function nota(string $etiqueta, string $valor): string
    {
        $valor = trim($valor);

        return $valor === '' ? '' : $etiqueta . ': ' . $valor;
    }

    /** Literal SQL (MariaDB/MySQL) o NULL. */
    private static function q(?string $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        return "'" . strtr($valor, ['\\' => '\\\\', "'" => "''", "\0" => '', "\r" => '']) . "'";
    }

    private static function rango(mixed $valor, int $min, int $max): ?int
    {
        return is_int($valor) && $valor >= $min && $valor <= $max ? $valor : null;
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

$archivo = null;
$salida = null;
$usuario = 'admin';
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--usuario=')) {
        $usuario = substr($arg, 10);
    } elseif ($archivo === null) {
        $archivo = $arg;
    } else {
        $salida = $arg;
    }
}

if ($archivo === null || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php database/generar_sql_equipos.php \"C:\\ruta\\INVENTARIO 2026.xlsx\" [salida.sql] [--usuario=admin]\n");
    exit(1);
}
$salida ??= __DIR__ . '/importar_equipos_' . date('Ymd_His') . '.sql';

try {
    $generador = new GeneradorSqlEquipos(new LectorXlsx($archivo));
    file_put_contents($salida, $generador->generar($archivo, $usuario));
} catch (Throwable $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "SQL generado: $salida\n\nEquipos:\n";
foreach ($generador->equiposPorHoja as $hoja => $total) {
    printf("  %-18s %d\n", $hoja, $total);
}
printf("  %-18s %d\n", 'TOTAL', array_sum($generador->equiposPorHoja));
echo "\nFilas omitidas (" . count($generador->omitidas) . "):\n";
foreach ($generador->omitidas as $o) {
    echo "  - $o\n";
}
echo "\nAvisos (" . count($generador->avisos) . "):\n";
foreach ($generador->avisos as $a) {
    echo "  - $a\n";
}
