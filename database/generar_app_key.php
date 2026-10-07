<?php

declare(strict_types=1);

/*
 * Genera la clave APP_KEY que cifra las contraseñas de las cuentas Microsoft 365.
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\xampp\php\php.exe database\generar_app_key.php             -> solo muestra una clave nueva
 *   C:\xampp\php\php.exe database\generar_app_key.php --escribir  -> la agrega al .env si aún no tiene APP_KEY
 *
 * IMPORTANTE: la misma APP_KEY debe usarse en todos los servidores que compartan la base de datos.
 * Si se cambia o se pierde, las contraseñas guardadas ya no se pueden descifrar.
 */

use App\Core\Cifrado;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
require $raiz . '/vendor/autoload.php';

$clave = Cifrado::generarClave();

if (!in_array('--escribir', array_slice($argv, 1), true)) {
    echo 'APP_KEY=' . $clave . PHP_EOL;
    echo 'Copie esta línea en el .env (use la misma clave en todos los servidores con la misma base).' . PHP_EOL;
    exit(0);
}

$env = $raiz . '/.env';
if (!is_file($env)) {
    fwrite(STDERR, "No existe el archivo .env. Copie .env.example como .env primero.\n");
    exit(1);
}
$contenido = (string) file_get_contents($env);
if (preg_match('/^\s*APP_KEY\s*=\s*\S+/m', $contenido) === 1) {
    fwrite(STDERR, "El .env ya tiene APP_KEY. No se reemplaza: cambiarla impediría descifrar las contraseñas guardadas.\n");
    exit(1);
}

$contenido = preg_match('/^\s*APP_KEY\s*=\s*$/m', $contenido) === 1
    ? (string) preg_replace('/^\s*APP_KEY\s*=\s*$/m', 'APP_KEY=' . $clave, $contenido)
    : rtrim($contenido) . PHP_EOL . PHP_EOL . '# Clave de cifrado (contraseñas de cuentas Microsoft 365). No la cambie ni la pierda.' . PHP_EOL . 'APP_KEY=' . $clave . PHP_EOL;
file_put_contents($env, $contenido);
echo 'APP_KEY agregada al .env.' . PHP_EOL;
