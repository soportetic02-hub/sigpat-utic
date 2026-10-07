<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Error HTTP controlado (403, 404, 419, 500...). El front controller lo
 * convierte en la vista de error correspondiente o en una respuesta JSON.
 */
final class HttpException extends RuntimeException
{
    public function __construct(private readonly int $status, string $mensaje = '')
    {
        parent::__construct($mensaje !== '' ? $mensaje : self::mensajePorDefecto($status), $status);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function mensajePorDefecto(int $status): string
    {
        return match ($status) {
            400     => 'Solicitud no válida.',
            401     => 'Debe iniciar sesión.',
            403     => 'No tiene permiso para acceder a este recurso.',
            404     => 'La página solicitada no existe.',
            405     => 'Método no permitido.',
            419     => 'La página expiró. Vuelva a intentarlo.',
            default => 'Ocurrió un error inesperado.',
        };
    }
}
