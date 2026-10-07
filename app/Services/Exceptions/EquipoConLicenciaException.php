<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use DomainException;

/**
 * El equipo ya ocupa una instalación vigente de otra cuenta Microsoft 365.
 */
final class EquipoConLicenciaException extends DomainException
{
    public function __construct(string $mensaje = 'El equipo ya ocupa una instalación de otra cuenta Microsoft 365. Libérela antes de asignarle otra.')
    {
        parent::__construct($mensaje);
    }
}
