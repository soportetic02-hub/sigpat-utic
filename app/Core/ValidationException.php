<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Errores de validación de negocio. Los Services la lanzan con un arreglo
 * campo => mensaje; los Controllers la capturan y vuelven al formulario.
 */
final class ValidationException extends RuntimeException
{
    /** @param array<string, string> $errores */
    public function __construct(private readonly array $errores, string $mensaje = 'Revise los datos del formulario.')
    {
        parent::__construct($mensaje);
    }

    /** @return array<string, string> */
    public function getErrores(): array
    {
        return $this->errores;
    }

    public static function campo(string $campo, string $mensaje): self
    {
        return new self([$campo => $mensaje], $mensaje);
    }
}
