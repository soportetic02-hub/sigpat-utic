<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Catálogo administrable de software instalable.
 */
final class CatalogoSoftwareModel extends Model
{
    protected string $table = 'catalogo_software';

    protected array $fillable = [
        'nombre',
        'descripcion',
        'orden',
        'activo',
    ];

    /**
     * Catálogo completo con el número de actas que usan cada software.
     *
     * @return list<array<string, mixed>>
     */
    public function listadoCompleto(): array
    {
        return $this->db->run(
            'SELECT c.id, c.nombre, c.descripcion, c.orden, c.activo,
                    (SELECT COUNT(*) FROM mantenimiento_software ms WHERE ms.software_id = c.id) AS usos
               FROM catalogo_software c
              ORDER BY c.activo DESC, c.orden, c.nombre'
        )->fetchAll();
    }

    /**
     * Software activo, más los ids indicados aunque estén inactivos (ya usados en un acta).
     *
     * @param list<int> $incluirIds
     * @return list<array<string, mixed>>
     */
    public function activos(array $incluirIds = []): array
    {
        $incluir = array_values(array_filter(array_map('intval', $incluirIds), static fn (int $i): bool => $i > 0));
        $sql = 'SELECT id, nombre, activo FROM catalogo_software WHERE activo = 1';
        $params = [];
        foreach ($incluir as $i => $id) {
            $params['inc' . $i] = $id;
        }
        if ($params !== []) {
            $sql .= ' OR id IN (:' . implode(', :', array_keys($params)) . ')';
        }

        return $this->db->run($sql . ' ORDER BY orden, nombre', $params)->fetchAll();
    }
}
