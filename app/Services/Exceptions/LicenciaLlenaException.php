<?php

declare(strict_types=1);

namespace App\Services\Exceptions;

use DomainException;

/**
 * La cuenta Microsoft 365 ya tiene sus 5 instalaciones ocupadas (o el slot pedido se ocupó en paralelo).
 */
final class LicenciaLlenaException extends DomainException
{
    public function __construct(string $mensaje = 'La cuenta ya tiene todas sus instalaciones ocupadas. Libere una antes de asignar otro equipo.')
    {
        parent::__construct($mensaje);
    }
}
