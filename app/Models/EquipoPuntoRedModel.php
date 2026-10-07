<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Puntos de red de cada equipo.
 */
final class EquipoPuntoRedModel extends Model
{
    protected string $table = 'equipo_puntos_red';

    protected array $fillable = [
        'equipo_id',
        'codigo_punto',
        'switch_puerto',
        'vlan',
        'observacion',
    ];

    /** @return list<array<string, mixed>> */
    public function porEquipo(int $equipoId): array
    {
        return $this->db->run(
            'SELECT id, codigo_punto, switch_puerto, vlan, observacion
               FROM equipo_puntos_red WHERE equipo_id = :equipo ORDER BY codigo_punto',
            ['equipo' => $equipoId]
        )->fetchAll();
    }

    /**
     * Reemplaza todos los puntos de red del equipo.
     *
     * @param list<array{codigo_punto: string, switch_puerto: ?string, vlan: ?string, observacion: ?string}> $puntos
     */
    public function reemplazar(int $equipoId, array $puntos): void
    {
        $this->db->run('DELETE FROM equipo_puntos_red WHERE equipo_id = :equipo', ['equipo' => $equipoId]);
        foreach ($puntos as $p) {
            $this->insert(['equipo_id' => $equipoId] + $p);
        }
    }
}
