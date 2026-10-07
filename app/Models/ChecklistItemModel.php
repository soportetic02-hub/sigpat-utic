<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Catálogo de actividades de mantenimiento (FISICO, LOGICO, RED).
 */
final class ChecklistItemModel extends Model
{
    public const CATEGORIAS = ['FISICO', 'LOGICO', 'RED'];
    public const CODIGO_FORMATEO = 'FORMATEO_SO';

    protected string $table = 'checklist_items';

    protected array $fillable = [
        'codigo',
        'categoria',
        'descripcion',
        'aplica_a',
        'orden',
        'activo',
    ];

    /**
     * Ítems activos que aplican a un tipo de equipo, agrupados por categoría.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function activosPorTipo(string $tipoEquipo): array
    {
        $filas = $this->db->run(
            'SELECT id, codigo, categoria, descripcion, aplica_a, orden
               FROM checklist_items
              WHERE activo = 1 AND FIND_IN_SET(:tipo, aplica_a) > 0
              ORDER BY FIELD(categoria, \'FISICO\', \'LOGICO\', \'RED\'), orden, descripcion',
            ['tipo' => $tipoEquipo]
        )->fetchAll();

        $grupos = array_fill_keys(self::CATEGORIAS, []);
        foreach ($filas as $fila) {
            $grupos[$fila['categoria']][] = $fila;
        }

        return $grupos;
    }

    /**
     * Catálogo completo con el número de actas que usan cada ítem.
     *
     * @return list<array<string, mixed>>
     */
    public function listadoCompleto(): array
    {
        return $this->db->run(
            "SELECT c.id, c.codigo, c.categoria, c.descripcion, c.aplica_a, c.orden, c.activo,
                    (SELECT COUNT(*) FROM mantenimiento_checklist mc WHERE mc.checklist_item_id = c.id) AS usos
               FROM checklist_items c
              ORDER BY FIELD(c.categoria, 'FISICO', 'LOGICO', 'RED'), c.activo DESC, c.orden, c.descripcion"
        )->fetchAll();
    }

    public function existeDescripcion(string $categoria, string $descripcion, ?int $excluirId): bool
    {
        $sql = 'SELECT 1 FROM checklist_items WHERE categoria = :categoria AND descripcion = :descripcion';
        $params = ['categoria' => $categoria, 'descripcion' => $descripcion];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $params['excluir'] = $excluirId;
        }

        return $this->db->run($sql . ' LIMIT 1', $params)->fetchColumn() !== false;
    }

    public function idFormateo(): ?int
    {
        $id = $this->db->run(
            'SELECT id FROM checklist_items WHERE codigo = :codigo LIMIT 1',
            ['codigo' => self::CODIGO_FORMATEO]
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }
}
