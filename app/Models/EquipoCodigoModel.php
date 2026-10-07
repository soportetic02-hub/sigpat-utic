<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Códigos anuales de inventario (equipo_id, anio, codigo).
 */
final class EquipoCodigoModel extends Model
{
    protected string $table = 'equipo_codigos';

    protected array $fillable = [
        'equipo_id',
        'anio',
        'codigo',
    ];

    /** @return list<array<string, mixed>> */
    public function porEquipo(int $equipoId): array
    {
        return $this->db->run(
            'SELECT id, anio, codigo FROM equipo_codigos WHERE equipo_id = :equipo ORDER BY anio DESC',
            ['equipo' => $equipoId]
        )->fetchAll();
    }

    /** Equipo que ya usa ese código en ese año (distinto del indicado), o null. */
    public function equipoConCodigo(int $anio, string $codigo, ?int $excluirEquipoId): ?int
    {
        $sql = 'SELECT equipo_id FROM equipo_codigos WHERE anio = :anio AND codigo = :codigo';
        $params = ['anio' => $anio, 'codigo' => $codigo];
        if ($excluirEquipoId !== null) {
            $sql .= ' AND equipo_id <> :excluir';
            $params['excluir'] = $excluirEquipoId;
        }
        $id = $this->db->run($sql . ' LIMIT 1', $params)->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Reemplaza todos los códigos del equipo.
     *
     * @param list<array{anio: int, codigo: string}> $codigos
     */
    public function reemplazar(int $equipoId, array $codigos): void
    {
        $this->db->run('DELETE FROM equipo_codigos WHERE equipo_id = :equipo', ['equipo' => $equipoId]);
        foreach ($codigos as $c) {
            $this->insert(['equipo_id' => $equipoId, 'anio' => $c['anio'], 'codigo' => $c['codigo']]);
        }
    }
}
