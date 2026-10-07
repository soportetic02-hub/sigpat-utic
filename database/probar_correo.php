<?php

declare(strict_types=1);

/*
 * Prueba la configuración del correo saliente (variables MAIL_* del .env).
 *
 * Uso (desde la carpeta del proyecto):
 *   C:\xampp\php\php.exe database\probar_correo.php destino@caen.edu.pe
 *
 * Envía un correo de prueba sin adjuntos y muestra el error del servidor si falla.
 * No escribe nada en la base de datos.
 */

use App\Services\CorreoService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require dirname(__DIR__) . '/vendor/autoload.php';

$destino = $argv[1] ?? '';
if (filter_var($destino, FILTER_VALIDATE_EMAIL) === false) {
    fwrite(STDERR, "Uso: php database/probar_correo.php destino@caen.edu.pe\n");
    exit(1);
}

echo 'Servidor:  ' . config('mail.host') . ':' . config('mail.port') . ' (' . config('mail.encryption') . ")\n";
echo 'Remitente: ' . config('mail.from_address') . ' <' . config('mail.from_name') . ">\n";
echo 'Usuario:   ' . (config('mail.username') !== '' ? config('mail.username') : '(sin autenticación)') . "\n";

try {
    (new CorreoService())->enviar(
        $destino,
        '',
        'Prueba de correo - SIGPAT-OTIC',
        '<p>Este es un correo de prueba de <strong>SIGPAT-OTIC</strong>. La configuración del correo saliente funciona.</p>',
        'Este es un correo de prueba de SIGPAT-OTIC. La configuración del correo saliente funciona.'
    );
} catch (RuntimeException $e) {
    fwrite(STDERR, 'ERROR: ' . $e->getMessage() . "\n");
    exit(1);
}

echo "Correo enviado a {$destino}. Revise la bandeja de entrada (y la carpeta de spam).\n";
