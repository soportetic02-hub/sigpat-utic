<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Respuestas HTTP. Cada método envía la respuesta y termina la ejecución.
 */
final class Response
{
    /** Redirige a una ruta interna ('usuarios') o a una URL absoluta. */
    public static function redirect(string $destino, int $status = 302): never
    {
        $url = preg_match('#^https?://#i', $destino) === 1 ? $destino : url($destino);
        header('Location: ' . $url, true, $status);
        exit;
    }

    /** Vuelve a la página anterior (misma aplicación) o a la ruta indicada. */
    public static function back(string $alternativa = '/'): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $base = (string) config('app.url');
        if ($referer !== '' && str_starts_with($referer, $base)) {
            self::redirect($referer);
        }
        self::redirect($alternativa);
    }

    /** @param array<mixed>|object $datos */
    public static function json(array|object $datos, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        exit;
    }

    /**
     * Envía un archivo del servidor. $inline = true lo muestra en el navegador
     * (vista previa de PDF/imagen); false fuerza la descarga.
     */
    public static function download(string $rutaArchivo, string $nombreDescarga, bool $inline = false, ?string $mime = null): never
    {
        if (!is_file($rutaArchivo) || !is_readable($rutaArchivo)) {
            throw new HttpException(404, 'El archivo solicitado no existe.');
        }

        $mime ??= (new \finfo(FILEINFO_MIME_TYPE))->file($rutaArchivo) ?: 'application/octet-stream';
        $nombre = str_replace(['"', "\r", "\n", '/', '\\'], '', $nombreDescarga);
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?? 'archivo';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($rutaArchivo));
        header(sprintf(
            'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $inline ? 'inline' : 'attachment',
            $ascii,
            rawurlencode($nombre)
        ));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        readfile($rutaArchivo);
        exit;
    }

    /** Envía contenido generado en memoria (CSV, PDF, DOCX) como descarga o inline. */
    public static function content(string $contenido, string $nombreDescarga, string $mime, bool $inline = false): never
    {
        $nombre = str_replace(['"', "\r", "\n", '/', '\\'], '', $nombreDescarga);
        $ascii = preg_replace('/[^A-Za-z0-9._-]/', '_', $nombre) ?? 'archivo';

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) strlen($contenido));
        header(sprintf(
            'Content-Disposition: %s; filename="%s"; filename*=UTF-8\'\'%s',
            $inline ? 'inline' : 'attachment',
            $ascii,
            rawurlencode($nombre)
        ));
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: private, no-store');
        echo $contenido;
        exit;
    }
}
