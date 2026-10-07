<?php

declare(strict_types=1);

/*
 * Importa las cuentas Microsoft 365 de la hoja de cálculo "Licencias Office 2026.xlsx".
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\xampp\php\php.exe database\importar_licencias.php "C:\ruta\Licencias Office 2026.xlsx" --dry-run  -> simulación (no guarda nada)
 *   C:\xampp\php\php.exe database\importar_licencias.php "C:\ruta\Licencias Office 2026.xlsx"            -> guarda los datos
 *
 * Opciones:
 *   --dry-run          recorre todo y muestra el reporte, pero revierte la transacción (no escribe nada).
 *   --usuario=admin    usuario del sistema que figurará como autor en la auditoría (por defecto "admin").
 *
 * Requiere APP_KEY en el .env (las contraseñas se guardan cifradas con AES-256-GCM).
 *
 * Formato esperado (todas las hojas): una fila de cabecera con "ID" en la columna B y, debajo,
 * 5 filas por cuenta (Lic. usadas 1/5 ... 5/5):
 *   B ID (licencia01)  C Nombre de usuario (correo)  D Contraseña de fábrica  E Licencias (plan)
 *   F Usuario asignado  J Equipo asignado  K Oficina  L Nueva Contraseña  M Lic. usadas
 * Los datos vigentes de cada instalación son F/J/K. Las columnas G/H/I (USUARIO / EQUIPO /
 * NOMBRE DEL EQUIPO, marcadas 2026) solo se guardan como observación.
 *
 * Todo se hace en UNA transacción usando LicenciaService (mismas validaciones y auditoría que
 * el formulario web): o se importa todo o nada. Las cuentas cuyo correo o código ya existe se omiten.
 */

use App\Config\Database;
use App\Config\DatabaseException;
use App\Models\LicenciaModel;
use App\Services\LicenciaService;

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

final class ImportadorLicencias
{
    /** Columnas de la cabecera (texto normalizado esperado). */
    private const CABECERA = [
        'B' => 'ID', 'C' => 'NOMBRE DE USUARIO', 'D' => 'CONTRASENA DE FABRICA', 'E' => 'LICENCIAS',
        'F' => 'USUARIO ASIGNADO', 'J' => 'EQUIPO ASIGNADO', 'K' => 'OFICINA', 'L' => 'NUEVA CONTRASENA', 'M' => 'LIC USADAS',
    ];

    /**
     * Oficinas escritas de otra forma en el Excel (texto normalizado => nombre en la tabla oficinas).
     * Lo que no esté aquí se busca por nombre o siglas exactos y, después, tolerando errores de tipeo.
     */
    private const ALIAS_OFICINAS = [
        'RRHH'                     => 'Dpto. Recursos Humanos',
        'DIPLOMADOS'               => 'Dpto. Diplomados',
        'EVALUACION'               => 'Dpto. Evaluación',
        'LOGISTICA'                => 'Dpto. Logística y Abasto',
        'ABASTECIMIENTO'           => 'Dpto. Logística y Abasto',
        'ABASTECMIENTO'            => 'Dpto. Logística y Abasto',
        'FINANZAS'                 => 'Dpto. Finanzas',
        'ACADEMICO'                => 'Dirección Académica',
        'DIR ACADEMICA'            => 'Dirección Académica',
        'ADMINISTR'                => 'Dirección Administrativa',
        'ADMINISTRATIVO'           => 'Dirección Administrativa',
        'DIR GENERAL'              => 'Dirección General',
        'DIRECCION INVESTIACION'   => 'Dirección de Investigación',
        'INST MARIN'               => 'Instituto General Marín',
        'CALIDAD'                  => 'Oficina de Calidad',
        'CALIDAD EDUCATIVA'        => 'Oficina de Calidad',
        'ENFERMERIA'               => 'Tópico',
        'ASE JURIDICA'             => 'Asesoría Jurídica',
        'CAPTACION Y DIFUSION'     => 'Captación',
        'ADMISION Y MATRICULAS'    => 'División de Matrículas',
    ];

    /** Títulos que se ignoran al comparar nombres de personas. */
    private const TITULOS = ['DR', 'DRA', 'ING', 'LIC', 'MG', 'SR', 'SRA', 'SRTA'];

    private Database $db;
    private LicenciaService $servicio;

    /** @var list<array{id: int, nombre: string, tokens: list<string>}> */
    private array $personal = [];
    /** @var array<string, array<string, mixed>> hostname/código canónico => equipo */
    private array $equiposPorClave = [];
    /** @var array<string, int> clave de oficina => id */
    private array $oficinaPorClave = [];
    /** @var array<int, string> */
    private array $oficinaNombre = [];
    /** @var array<int, string> equipo_id => "licenciaXX slot N" ya asignado en esta importación */
    private array $equiposUsados = [];

    public int $cuentasCreadas = 0;
    public int $slotsCreados = 0;
    /** @var array<string, int> */
    public array $vinculados = ['equipo' => 0, 'persona' => 0, 'oficina' => 0];
    /** @var list<string> */
    public array $cuentasOmitidas = [];
    /** @var list<string> */
    public array $porVerificar = [];
    /** @var array<string, list<string>> texto del Excel => referencias */
    public array $equiposNoEncontrados = [];
    /** @var array<string, list<string>> */
    public array $personasNoEncontradas = [];
    /** @var array<string, list<string>> */
    public array $oficinasNoEncontradas = [];
    /** @var array<string, string> texto del Excel => registro emparejado (coincidencias no exactas) */
    public array $aproximados = [];
    /** @var list<string> */
    public array $avisos = [];

    public function __construct(private readonly LectorXlsx $lector, private readonly int $usuarioId)
    {
        $this->db = Database::getInstance();
        $this->servicio = new LicenciaService();
        $this->cargarCatalogos();
    }

    public function importar(): void
    {
        foreach ($this->lector->nombresHojas() as $hoja) {
            foreach ($this->cuentasDeHoja($hoja) as $codigo => $cuenta) {
                $this->importarCuenta($hoja, $codigo, $cuenta);
            }
        }
    }

    // ------------------------------------------------------------------ lectura del Excel

    /**
     * Agrupa las filas de la hoja por cuenta (columna B).
     *
     * @return array<string, array{correo: string, fabrica: string, nueva: string, plan: string, filas: array<int, array<string, string>>}>
     */
    private function cuentasDeHoja(string $hoja): array
    {
        $filas = $this->lector->hoja($hoja);
        $inicio = null;
        foreach ($filas as $n => $f) {
            if (self::clave($f['B'] ?? '') === 'ID') {
                $inicio = $n;
                break;
            }
        }
        if ($inicio === null) {
            $this->avisos[] = sprintf('Hoja "%s": no tiene la cabecera "ID" en la columna B; se omitió.', $hoja);

            return [];
        }
        foreach (self::CABECERA as $col => $texto) {
            if (!str_starts_with(self::clave($filas[$inicio][$col] ?? ''), $texto)) {
                throw new RuntimeException(sprintf(
                    'La hoja "%s" cambió de formato: se esperaba "%s" en la columna %s de la fila %d y hay "%s".',
                    $hoja, $texto, $col, $inicio, $filas[$inicio][$col] ?? ''
                ));
            }
        }

        $cuentas = [];
        foreach ($filas as $n => $f) {
            $codigo = trim($f['B'] ?? '');
            if ($n <= $inicio || $codigo === '') {
                continue;
            }
            $cuentas[$codigo] ??= [
                'correo'  => mb_strtolower(trim($f['C'] ?? '')),
                'fabrica' => trim($f['D'] ?? ''),
                'nueva'   => trim($f['L'] ?? ''),
                'plan'    => trim($f['E'] ?? ''),
                'filas'   => [],
            ];
            $cuentas[$codigo]['filas'][$n] = $f;
        }

        return $cuentas;
    }

    // ------------------------------------------------------------------ cuentas y slots

    /** @param array{correo: string, fabrica: string, nueva: string, plan: string, filas: array<int, array<string, string>>} $cuenta */
    private function importarCuenta(string $hoja, string $codigo, array $cuenta): void
    {
        $existente = $this->db->run(
            'SELECT codigo, correo FROM licencias_office WHERE codigo = :c OR correo = :m LIMIT 1',
            ['c' => $codigo, 'm' => $cuenta['correo']]
        )->fetch();
        if ($existente !== false) {
            $this->cuentasOmitidas[] = sprintf('%s (%s): ya existe en el sistema (%s); no se modificó.', $codigo, $cuenta['correo'], $existente['correo']);

            return;
        }

        // Filas de slots: el número sale de "Lic. usadas" (3/5); si falta, del orden.
        $slots = [];
        $orden = 0;
        foreach ($cuenta['filas'] as $n => $f) {
            $orden++;
            $numero = preg_match('/^(\d)\s*\/\s*\d$/', trim($f['M'] ?? ''), $m) === 1 ? (int) $m[1] : $orden;
            if ($numero < 1 || $numero > LicenciaModel::MAX_SLOTS || isset($slots[$numero])) {
                $this->avisos[] = sprintf('%s fila %d (%s): número de licencia "%s" inválido o repetido; se omitió la fila.', $hoja, $n, $codigo, $f['M'] ?? '');
                continue;
            }
            $slots[$numero] = [$n, $f];
        }

        $notasCuenta = [sprintf('[Importado de Excel: %s]', $hoja)];
        $instalaciones = [];
        foreach ($slots as $numero => [$n, $f]) {
            $ref = sprintf('%s slot %d (%s fila %d)', $codigo, $numero, $hoja, $n);
            $datos = $this->instalacion($ref, $codigo, $numero, $f);
            if ($datos === null) {
                // Slot libre en F/J/K: si G/H/I (2026) traen algo, queda como nota de la cuenta.
                $nota2026 = self::nota2026($f);
                if ($nota2026 !== null) {
                    $notasCuenta[] = sprintf('Slot %d libre; %s', $numero, $nota2026);
                }
                continue;
            }
            $instalaciones[$numero] = $datos;
        }

        $contrasena = self::limpio($cuenta['nueva']) !== null ? $cuenta['nueva'] : (self::limpio($cuenta['fabrica']) !== null ? $cuenta['fabrica'] : '');
        if ($contrasena === '') {
            $this->avisos[] = sprintf('%s: sin contraseña en el Excel.', $codigo);
        }

        $id = $this->servicio->crear([
            'codigo'          => $codigo,
            'correo'          => $cuenta['correo'],
            'plan'            => self::limpio($cuenta['plan']) ?? LicenciaService::PLAN_DEFECTO,
            'estado'          => 'ACTIVA',
            'password_cuenta' => $contrasena,
            'observaciones'   => mb_substr(implode("\n", $notasCuenta), 0, 2000),
        ], $this->usuarioId);
        $this->verificarTransaccion($codigo);
        $this->cuentasCreadas++;

        foreach ($instalaciones as $numero => $datos) {
            $this->servicio->asignarSlot($id, $datos, $this->usuarioId, $numero);
            $this->verificarTransaccion($codigo . ' slot ' . $numero);
            $this->slotsCreados++;
            if ($datos['equipo_id'] !== null) {
                $this->equiposUsados[$datos['equipo_id']] = $codigo . ' slot ' . $numero;
            }
        }
    }

    /**
     * Datos de una instalación a partir de las columnas F/J/K, o null si el slot está libre.
     *
     * @param array<string, string> $f
     * @return array<string, mixed>|null
     */
    private function instalacion(string $ref, string $codigo, int $numero, array $f): ?array
    {
        $personaTexto = trim($f['F'] ?? '');
        $equipoTexto = trim($f['J'] ?? '');
        $oficinaTexto = self::limpio($f['K'] ?? '');

        $verificar = false;
        if (self::clave($personaTexto) === 'VERIFICAR') {
            $verificar = true;
            $personaTexto = '';
        }
        if (preg_match('/\bREVISAR\b/i', $equipoTexto) === 1) {
            $verificar = true;
            $equipoTexto = trim((string) preg_replace('/[\s\-]*\bREVISAR\b[\s\-]*/i', ' ', $equipoTexto));
        }
        $persona = self::limpio($personaTexto);
        $equipo = self::limpio($equipoTexto);

        if ($persona === null && $equipo === null && !$verificar) {
            return null;
        }

        $notas = [];
        $datos = [
            'equipo_id'           => '',
            'equipo_texto'        => '',
            'personal_id'         => '',
            'usuario_texto'       => '',
            'oficina_id'          => '',
            'estado_verificacion' => $verificar ? 'POR_VERIFICAR' : 'OK',
            'observacion'         => '',
        ];

        // Equipo
        if ($equipo === null) {
            $datos['equipo_texto'] = 'Sin especificar';
            $datos['estado_verificacion'] = 'POR_VERIFICAR';
            $notas[] = 'El Excel no indica el equipo';
        } else {
            $encontrado = $this->buscarEquipo($equipo);
            if ($encontrado === null) {
                $datos['equipo_texto'] = mb_substr(self::textoEquipo($equipo), 0, 100);
                $this->equiposNoEncontrados[$equipo][] = $ref;
            } elseif (!in_array($encontrado['tipo'], LicenciaService::TIPOS_ELEGIBLES, true) || $encontrado['estado_operativo'] === 'DE_BAJA') {
                $datos['equipo_texto'] = mb_substr(self::textoEquipo($equipo), 0, 100);
                $datos['estado_verificacion'] = 'POR_VERIFICAR';
                $notas[] = sprintf('El equipo #%d del inventario es %s %s y no admite licencia', $encontrado['id'], $encontrado['tipo'], mb_strtolower(etiqueta((string) $encontrado['estado_operativo'])));
                $this->avisos[] = sprintf('%s: %s coincide con el equipo #%d, que no admite licencia (%s, %s); se guardó como texto.', $ref, $equipo, $encontrado['id'], $encontrado['tipo'], $encontrado['estado_operativo']);
            } elseif (($otro = $this->equipoOcupado((int) $encontrado['id'])) !== null) {
                $datos['equipo_texto'] = mb_substr(self::textoEquipo($equipo), 0, 100);
                $datos['estado_verificacion'] = 'POR_VERIFICAR';
                $notas[] = sprintf('Equipo repetido: también figura en %s', $otro);
                $this->avisos[] = sprintf('%s: %s ya tiene licencia en %s (un equipo solo puede tener una); se guardó como texto por verificar.', $ref, $equipo, $otro);
            } else {
                $datos['equipo_id'] = (string) $encontrado['id'];
                $this->vinculados['equipo']++;
            }
        }

        // Persona
        if ($persona !== null) {
            $personalId = $this->buscarPersona($persona);
            if ($personalId !== null) {
                $datos['personal_id'] = (string) $personalId;
                $this->vinculados['persona']++;
            } else {
                $datos['usuario_texto'] = self::textoPersona($persona);
                $this->personasNoEncontradas[$persona][] = $ref;
            }
        }

        // Oficina
        if ($oficinaTexto !== null) {
            $oficinaId = $this->buscarOficina($oficinaTexto);
            if ($oficinaId !== null) {
                $datos['oficina_id'] = (string) $oficinaId;
                $this->vinculados['oficina']++;
            } else {
                $notas[] = 'Oficina en el Excel: ' . $oficinaTexto;
                $this->oficinasNoEncontradas[$oficinaTexto][] = $ref;
            }
        }

        $nota2026 = self::nota2026($f);
        if ($nota2026 !== null) {
            $notas[] = $nota2026;
        }
        $datos['observacion'] = mb_substr(implode(' · ', $notas), 0, 255);

        if ($datos['estado_verificacion'] === 'POR_VERIFICAR') {
            $this->porVerificar[] = sprintf('%s: %s / %s', $ref, $equipo ?? 'sin equipo', $persona ?? 'sin persona');
        }

        return $datos;
    }

    /** Referencia de la instalación vigente del equipo (en la BD o en esta importación), o null. */
    private function equipoOcupado(int $equipoId): ?string
    {
        if (isset($this->equiposUsados[$equipoId])) {
            return $this->equiposUsados[$equipoId];
        }
        $fila = $this->db->run(
            'SELECT l.codigo, le.slot FROM licencia_equipos le INNER JOIN licencias_office l ON l.id = le.licencia_id WHERE le.equipo_activo = :e',
            ['e' => $equipoId]
        )->fetch();

        return $fila === false ? null : $fila['codigo'] . ' slot ' . $fila['slot'];
    }

    // ------------------------------------------------------------------ emparejamiento

    private function cargarCatalogos(): void
    {
        foreach ($this->db->run('SELECT id, nombres, apellidos FROM personal')->fetchAll() as $p) {
            $nombre = $p['nombres'] . ' ' . $p['apellidos'];
            $this->personal[] = ['id' => (int) $p['id'], 'nombre' => $nombre, 'tokens' => self::tokens($nombre)];
        }

        foreach ($this->db->run('SELECT id, tipo, estado_operativo, hostname, codigo_interno FROM equipos')->fetchAll() as $e) {
            foreach ([$e['hostname'], $e['codigo_interno']] as $valor) {
                if ($valor !== null && $valor !== '') {
                    $this->equiposPorClave[self::claveEquipo((string) $valor)] ??= $e;
                }
            }
        }
        foreach ($this->db->run('SELECT c.codigo, e.id, e.tipo, e.estado_operativo FROM equipo_codigos c INNER JOIN equipos e ON e.id = c.equipo_id')->fetchAll() as $c) {
            $this->equiposPorClave[self::claveEquipo((string) $c['codigo'])] ??= $c;
        }

        foreach ($this->db->run('SELECT id, nombre, siglas FROM oficinas WHERE activo = 1')->fetchAll() as $o) {
            $id = (int) $o['id'];
            $this->oficinaNombre[$id] = (string) $o['nombre'];
            $this->oficinaPorClave[self::clave((string) $o['nombre'])] = $id;
            if ($o['siglas'] !== null) {
                $this->oficinaPorClave[self::clave((string) $o['siglas'])] ??= $id;
            }
        }
        foreach (self::ALIAS_OFICINAS as $alias => $nombre) {
            $id = $this->oficinaPorClave[self::clave($nombre)] ?? null;
            if ($id !== null) {
                $this->oficinaPorClave[$alias] ??= $id;
            }
        }
    }

    /** @return array<string, mixed>|null */
    private function buscarEquipo(string $texto): ?array
    {
        // "Laptop - PC2020-117" -> PC2020-117: si el texto contiene un código PCaaaa-n, se usa ese.
        if (preg_match('/\bPC-?\d{4}-\d+\b/i', $texto, $m) === 1) {
            $texto = $m[0];
        }

        return $this->equiposPorClave[self::claveEquipo($texto)] ?? null;
    }

    private function buscarPersona(string $texto): ?int
    {
        $tokens = array_values(array_diff(self::tokens($texto), self::TITULOS));
        if ($tokens === []) {
            return null;
        }
        $clave = implode(' ', $tokens);

        $exactos = array_filter($this->personal, static fn (array $p): bool => implode(' ', $p['tokens']) === $clave);
        if (count($exactos) === 1) {
            return reset($exactos)['id'];
        }

        // Todas las palabras del Excel (mínimo 2) están en el nombre registrado ("Cesar Llontop"), o todas
        // las del nombre registrado (mínimo 3) están en el Excel ("Laura Mixy Lopez Rojas"), tolerando un
        // error de tipeo por palabra. Solo se acepta si hay un único candidato.
        if (count($tokens) < 2) {
            return null;
        }
        $candidatos = [];
        foreach ($this->personal as $p) {
            if (self::contenidas($tokens, $p['tokens']) || (count($p['tokens']) >= 3 && self::contenidas($p['tokens'], $tokens))) {
                $candidatos[$p['id']] = $p['nombre'];
            }
        }
        if (count($candidatos) !== 1) {
            return null;
        }
        $this->aproximados[$texto] = (string) reset($candidatos);

        return (int) array_key_first($candidatos);
    }

    /**
     * ¿Cada palabra de $buscadas tiene una parecida en $en?
     *
     * @param list<string> $buscadas
     * @param list<string> $en
     */
    private static function contenidas(array $buscadas, array $en): bool
    {
        foreach ($buscadas as $t) {
            $coincide = false;
            foreach ($en as $otra) {
                if (self::palabraParecida($t, $otra)) {
                    $coincide = true;
                    break;
                }
            }
            if (!$coincide) {
                return false;
            }
        }

        return true;
    }

    private function buscarOficina(string $texto): ?int
    {
        $clave = self::clave($texto);
        if (isset($this->oficinaPorClave[$clave])) {
            return $this->oficinaPorClave[$clave];
        }

        // Errores de tipeo ("Direccionn General"): una sola oficina a distancia <= 2.
        if (mb_strlen($clave) < 6) {
            return null;
        }
        $candidatos = [];
        foreach ($this->oficinaPorClave as $otra => $id) {
            if (levenshtein($clave, $otra) <= 2) {
                $candidatos[$id] = true;
            }
        }
        if (count($candidatos) !== 1) {
            return null;
        }
        $id = (int) array_key_first($candidatos);
        $this->aproximados[$texto] = 'oficina ' . $this->oficinaNombre[$id];

        return $id;
    }

    private static function palabraParecida(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (min(strlen($a), strlen($b)) < 4) {
            return false;
        }
        // Una letra de más, de menos o cambiada; o dos letras intercambiadas (Yanida / Yadina).
        if (levenshtein($a, $b) <= 1) {
            return true;
        }
        $x = str_split($a);
        $y = str_split($b);
        sort($x);
        sort($y);

        return $x === $y && levenshtein($a, $b) <= 2;
    }

    // ------------------------------------------------------------------ utilidades

    /** Nota con los datos 2026 (G/H/I) de una fila, o null si no tienen nada útil. */
    private static function nota2026(array $f): ?string
    {
        $partes = [];
        foreach (['G' => 'usuario', 'I' => 'equipo', 'H' => 'tipo'] as $col => $etiqueta) {
            $valor = self::limpio($f[$col] ?? '');
            if ($valor !== null) {
                $partes[] = $etiqueta . ' ' . ($col === 'G' ? self::textoPersona($valor) : mb_strtoupper($valor));
            }
        }

        return $partes === [] ? null : 'Excel 2026: ' . implode(', ', $partes);
    }

    /** Si un servicio revirtió la transacción por un error de BD, se aborta todo. */
    private function verificarTransaccion(string $ref): void
    {
        if (!$this->db->inTransaction()) {
            throw new RuntimeException('Error de base de datos al procesar ' . $ref . '. Revise storage/logs/app.log.');
        }
    }

    /** Vacíos del Excel: "", "-", "?", "xxxx", "N/A", "LLENOOO…". */
    private static function limpio(string $valor): ?string
    {
        $valor = trim((string) preg_replace('/\s+/u', ' ', $valor));
        if ($valor === '' || preg_match('/^(-+|_+|\?+|x+|n\/?a|s\/?n)$/i', $valor) === 1 || preg_match('/^LLEN/i', $valor) === 1) {
            return null;
        }

        return $valor;
    }

    private static function clave(string $texto): string
    {
        $t = mb_strtoupper(trim($texto));
        $t = strtr($t, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
        $t = (string) preg_replace('/[^A-Z0-9]+/', ' ', $t);

        return trim($t);
    }

    /** @return list<string> */
    private static function tokens(string $texto): array
    {
        $clave = self::clave($texto);

        return $clave === '' ? [] : explode(' ', $clave);
    }

    /** Hostname canónico: sin espacios, "PC-2020-07" = "PC2020-07", "PC2020-079" = "PC2020-79". */
    private static function claveEquipo(string $texto): string
    {
        $t = mb_strtoupper((string) preg_replace('/\s+/', '', $texto));
        $t = (string) preg_replace('/^PC-(\d{4})/', 'PC$1', $t);

        return (string) preg_replace('/-0+(\d)/', '-$1', $t);
    }

    /** Hostnames y usuarios de Windows se guardan tal cual (en mayúsculas si parecen hostname). */
    private static function textoEquipo(string $texto): string
    {
        return preg_match('/^[A-Za-z0-9]+(-[A-Za-z0-9]+)+$/', $texto) === 1 && preg_match('/\d/', $texto) === 1
            ? mb_strtoupper($texto)
            : $texto;
    }

    /** Nombres en formato título; un texto sin espacios y con números (p. ej. un hostname) se deja en mayúsculas. */
    private static function textoPersona(string $texto): string
    {
        $texto = trim((string) preg_replace('/\s+/u', ' ', $texto));
        if (!str_contains($texto, ' ') && preg_match('/\d/', $texto) === 1) {
            return mb_substr(mb_strtoupper($texto), 0, 150);
        }
        $t = mb_convert_case(mb_strtolower($texto), MB_CASE_TITLE);
        $t = (string) preg_replace_callback('/(?<=\s)(De|Del|La|Las|Los|Y|E)(?=\s)/u', static fn (array $m): string => mb_strtolower($m[1]), $t);

        return mb_substr($t, 0, 150);
    }
}

// ---------------------------------------------------------------------- ejecución

$archivo = null;
$simulacion = false;
$login = 'admin';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $simulacion = true;
    } elseif (str_starts_with($arg, '--usuario=')) {
        $login = substr($arg, 10);
    } else {
        $archivo = $arg;
    }
}

if ($archivo === null || !is_file($archivo)) {
    fwrite(STDERR, "Uso: php database/importar_licencias.php \"C:\\ruta\\Licencias Office 2026.xlsx\" [--dry-run] [--usuario=admin]\n");
    exit(1);
}

$db = Database::getInstance();
$inicio = microtime(true);

try {
    $usuarioId = $db->run('SELECT id FROM usuarios_sistema WHERE usuario = :u AND activo = 1', ['u' => $login])->fetchColumn();
    if ($usuarioId === false) {
        throw new RuntimeException('No existe el usuario del sistema activo "' . $login . '" (use --usuario=...).');
    }
    // Falla aquí, antes de leer el Excel, si falta la APP_KEY.
    App\Core\Cifrado::cifrar('prueba');

    $importador = new ImportadorLicencias(new LectorXlsx($archivo), (int) $usuarioId);

    $db->beginTransaction();
    try {
        $importador->importar();
        if ($simulacion) {
            $db->rollBack();
        } else {
            $db->commit();
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
$lineas[] = $simulacion ? '=== SIMULACIÓN --dry-run (no se guardó nada) ===' : '=== IMPORTACIÓN REALIZADA ===';
$lineas[] = 'Archivo: ' . $archivo;
$lineas[] = '';
$lineas[] = sprintf('Cuentas creadas: %d', $importador->cuentasCreadas);
$lineas[] = sprintf('Instalaciones (slots) creadas: %d', $importador->slotsCreados);
$lineas[] = sprintf('  vinculadas a un equipo del inventario: %d', $importador->vinculados['equipo']);
$lineas[] = sprintf('  vinculadas a una persona de Personal:  %d', $importador->vinculados['persona']);
$lineas[] = sprintf('  vinculadas a una oficina:              %d', $importador->vinculados['oficina']);

$listar = static function (string $titulo, array $elementos) use (&$lineas): void {
    $lineas[] = '';
    $lineas[] = $titulo . ' (' . count($elementos) . '):';
    foreach ($elementos as $clave => $valor) {
        $lineas[] = is_array($valor) ? sprintf('  - %s  [%s]', $clave, implode('; ', $valor)) : '  - ' . $valor;
    }
};
$listar('Cuentas omitidas', $importador->cuentasOmitidas);
$listar('Instalaciones POR VERIFICAR', $importador->porVerificar);
$listar('Equipos no encontrados en el inventario (se guardaron como texto)', $importador->equiposNoEncontrados);
$listar('Personas no encontradas en Personal (se guardaron como texto)', $importador->personasNoEncontradas);
$listar('Coincidencias aproximadas (revise que sean correctas)', array_map(
    static fn (int|string $excel, string $registro): string => $excel . ' -> ' . $registro,
    array_keys($importador->aproximados),
    array_values($importador->aproximados)
));
$listar('Oficinas no encontradas (anotadas en la observación del slot)', $importador->oficinasNoEncontradas);
$listar('Avisos', $importador->avisos);
$lineas[] = '';
$lineas[] = sprintf('Tiempo: %.1f s', microtime(true) - $inicio);

$reporte = implode(PHP_EOL, $lineas) . PHP_EOL;
echo $reporte;

if (!$simulacion) {
    $rutaReporte = $raiz . '/storage/logs/importacion_licencias_' . date('Ymd_His') . '.txt';
    file_put_contents($rutaReporte, $reporte);
    echo 'Reporte guardado en: ' . $rutaReporte . PHP_EOL;
}
