<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * Registro simple en storage/logs/app.log.
 */
final class Logger
{
    public static function error(string $mensaje, array $contexto = []): void
    {
        self::escribir('ERROR', $mensaje, $contexto);
    }

    public static function warning(string $mensaje, array $contexto = []): void
    {
        self::escribir('WARNING', $mensaje, $contexto);
    }

    public static function info(string $mensaje, array $contexto = []): void
    {
        self::escribir('INFO', $mensaje, $contexto);
    }

    public static function exception(Throwable $e, string $mensaje = 'Excepción no controlada'): void
    {
        self::escribir('ERROR', $mensaje, [
            'tipo'    => $e::class,
            'mensaje' => $e->getMessage(),
            'archivo' => $e->getFile() . ':' . $e->getLine(),
            'traza'   => $e->getTraceAsString(),
            'previa'  => $e->getPrevious() !== null
                ? $e->getPrevious()::class . ': ' . $e->getPrevious()->getMessage()
                : null,
        ]);
    }

    private static function escribir(string $nivel, string $mensaje, array $contexto): void
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $linea = sprintf(
            "[%s] %s: %s%s\n",
            date('Y-m-d H:i:s'),
            $nivel,
            $mensaje,
            $contexto === [] ? '' : ' ' . json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        @file_put_contents($dir . DIRECTORY_SEPARATOR . 'app.log', $linea, FILE_APPEND | LOCK_EX);
    }
}
