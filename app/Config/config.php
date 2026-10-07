<?php

declare(strict_types=1);

/*
 * Configuración de SIGPAT-OTIC.
 * Lee el archivo .env de la raíz del proyecto, fija la zona horaria y el manejo
 * de errores según APP_ENV, y devuelve un arreglo de configuración.
 * Se carga una sola vez a través del helper config().
 */

$basePath = dirname(__DIR__, 2);

$env = [];
$envFile = $basePath . DIRECTORY_SEPARATOR . '.env';

if (!is_file($envFile)) {
    throw new RuntimeException('No existe el archivo .env. Copie .env.example como .env y configúrelo.');
}

$lineas = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($lineas === false ? [] : $lineas as $linea) {
    $linea = trim($linea);
    if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
        continue;
    }
    [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
    $largo = strlen($valor);
    if ($largo >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[$largo - 1] === $valor[0]) {
        $valor = substr($valor, 1, -1);
    }
    $env[$clave] = $valor;
}

$leer = static fn (string $clave, string $defecto = ''): string => $env[$clave] ?? $defecto;

$appEnv = strtolower($leer('APP_ENV', 'production')) === 'local' ? 'local' : 'production';
$debug = $appEnv === 'local';

date_default_timezone_set('America/Lima');
mb_internal_encoding('UTF-8');

$logDir = $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $logDir . DIRECTORY_SEPARATOR . 'app.log');

return [
    'app' => [
        'name'     => 'SIGPAT-OTIC',
        'url'      => rtrim($leer('APP_URL', 'http://localhost/sigpat-otic/public'), '/'),
        'env'      => $appEnv,
        'debug'    => $debug,
        'key'      => $leer('APP_KEY'),
        'timezone' => 'America/Lima',
    ],
    'db' => [
        'host'    => $leer('DB_HOST', '127.0.0.1'),
        'port'    => (int) $leer('DB_PORT', '3306'),
        'name'    => $leer('DB_NAME', 'sigpat_otic'),
        'user'    => $leer('DB_USER', 'root'),
        'pass'    => $leer('DB_PASS', ''),
        'charset' => 'utf8mb4',
        'tz'      => '-05:00',
    ],
    'mail' => [
        'host'         => $leer('MAIL_HOST'),
        'port'         => (int) $leer('MAIL_PORT', '587'),
        'encryption'   => strtolower($leer('MAIL_ENCRYPTION', 'tls')),
        'username'     => $leer('MAIL_USERNAME'),
        'password'     => $leer('MAIL_PASSWORD'),
        'from_address' => $leer('MAIL_FROM_ADDRESS', $leer('MAIL_USERNAME')),
        'from_name'    => $leer('MAIL_FROM_NAME', 'OTIC - CAEN-EPG'),
        'reply_to'     => $leer('MAIL_REPLY_TO'),
    ],
    'session' => [
        'name'              => 'SIGPATSESSID',
        'inactividad_segs'  => 30 * 60,
    ],
    'auth' => [
        'max_intentos'    => 5,
        'bloqueo_minutos' => 15,
    ],
    'paths' => [
        'base'       => $basePath,
        'views'      => $basePath . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Views',
        'storage'    => $basePath . DIRECTORY_SEPARATOR . 'storage',
        'logs'       => $logDir,
        'evidencias' => $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'evidencias',
        'reports'    => $basePath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'reports',
    ],
];
