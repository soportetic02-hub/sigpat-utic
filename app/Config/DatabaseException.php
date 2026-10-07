<?php

declare(strict_types=1);

namespace App\Config;

use PDOException;
use RuntimeException;

/**
 * Excepción propia de la capa de datos. Su mensaje es seguro para mostrarse
 * (no contiene SQL, credenciales ni detalles de PDO); el detalle queda en app.log.
 */
final class DatabaseException extends RuntimeException
{
    private string $sqlState;
    private int $driverCode;
    private string $driverMessage;

    public function __construct(string $mensaje, ?PDOException $previa = null)
    {
        $this->sqlState = (string) ($previa?->errorInfo[0] ?? $previa?->getCode() ?? '');
        $this->driverCode = (int) ($previa?->errorInfo[1] ?? 0);
        $this->driverMessage = (string) ($previa?->errorInfo[2] ?? $previa?->getMessage() ?? '');
        parent::__construct($mensaje, 0, $previa);
    }

    public function getSqlState(): string
    {
        return $this->sqlState;
    }

    public function getDriverCode(): int
    {
        return $this->driverCode;
    }

    /** Violación de integridad: UNIQUE, FK o CHECK (SQLSTATE 23000). */
    public function esViolacionIntegridad(): bool
    {
        return $this->sqlState === '23000';
    }

    /** Clave duplicada (MariaDB 1062). */
    public function esDuplicado(): bool
    {
        return $this->driverCode === 1062;
    }

    /** Fila referenciada por una FK con RESTRICT (MariaDB 1451). */
    public function esReferenciaEnUso(): bool
    {
        return $this->driverCode === 1451;
    }

    /**
     * Nombre del índice o constraint violado (uq_..., fk_..., chk_...), si se puede
     * determinar a partir del mensaje del driver. Útil para traducir a mensajes claros.
     */
    public function getConstraint(): ?string
    {
        if (preg_match("/for key '(?:[^'.]+\\.)?([^']+)'/", $this->driverMessage, $m) === 1) {
            return $m[1];
        }
        if (preg_match('/CONSTRAINT `([^`]+)`/', $this->driverMessage, $m) === 1) {
            return $m[1];
        }

        return null;
    }
}
