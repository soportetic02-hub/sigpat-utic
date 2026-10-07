<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Correlativos anuales por tipo de documento: (tipo, anio) -> ultimo.
 */
final class CorrelativoModel extends Model
{
    protected string $table = 'correlativos';
    protected string $primaryKey = 'tipo';

    protected array $fillable = [
        'tipo',
        'anio',
        'ultimo',
    ];

    /** Crea la fila del año si no existe (sin tocar una existente). */
    public function asegurar(string $tipo, int $anio): void
    {
        $this->db->run(
            'INSERT IGNORE INTO correlativos (tipo, anio, ultimo) VALUES (:tipo, :anio, 0)',
            ['tipo' => $tipo, 'anio' => $anio]
        );
    }

    /** Lee el último número bloqueando la fila hasta el fin de la transacción. */
    public function ultimoParaActualizar(string $tipo, int $anio): int
    {
        return (int) $this->db->run(
            'SELECT ultimo FROM correlativos WHERE tipo = :tipo AND anio = :anio FOR UPDATE',
            ['tipo' => $tipo, 'anio' => $anio]
        )->fetchColumn();
    }

    public function fijar(string $tipo, int $anio, int $ultimo): void
    {
        $this->db->run(
            'UPDATE correlativos SET ultimo = :ultimo WHERE tipo = :tipo AND anio = :anio',
            ['ultimo' => $ultimo, 'tipo' => $tipo, 'anio' => $anio]
        );
    }
}
