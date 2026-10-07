<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Oficinas (árbol mediante padre_id).
 */
final class OficinaModel extends Model
{
    protected string $table = 'oficinas';

    protected array $fillable = [
        'padre_id',
        'nombre',
        'siglas',
        'tipo',
        'ubicacion',
        'telefono',
        'activo',
    ];

    private const SELECT_CON_CONTEO = "
        o.id, o.padre_id, o.nombre, o.siglas, o.tipo, o.ubicacion, o.telefono, o.activo,
        pa.nombre AS padre_nombre,
        (SELECT COUNT(*) FROM personal p WHERE p.oficina_id = o.id AND p.activo = 1) AS personal_activos,
        (SELECT COUNT(*) FROM equipos e WHERE e.oficina_id = o.id AND e.estado_operativo <> 'DE_BAJA') AS equipos_activos,
        (SELECT COUNT(*) FROM oficinas h WHERE h.padre_id = o.id AND h.activo = 1) AS hijas_activas";

    /**
     * Todas las oficinas con conteos, para construir el árbol.
     *
     * @return list<array<string, mixed>>
     */
    public function todasConConteo(bool $incluirInactivas): array
    {
        $where = $incluirInactivas ? '' : 'WHERE o.activo = 1';

        return $this->db->run(
            'SELECT ' . self::SELECT_CON_CONTEO . "
               FROM oficinas o
               LEFT JOIN oficinas pa ON pa.id = o.padre_id
               {$where}
              ORDER BY o.nombre ASC"
        )->fetchAll();
    }

    /**
     * Listado plano paginado con filtros.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function buscar(string $texto, ?string $tipo, ?int $activo, int $page, int $perPage = 15): array
    {
        $condiciones = [];
        $params = [];
        if ($texto !== '') {
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $condiciones[] = '(o.nombre LIKE :q1 OR o.siglas LIKE :q2 OR o.ubicacion LIKE :q3)';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like];
        }
        if ($tipo !== null) {
            $condiciones[] = 'o.tipo = :tipo';
            $params['tipo'] = $tipo;
        }
        if ($activo !== null) {
            $condiciones[] = 'o.activo = :activo';
            $params['activo'] = $activo;
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'o.activo DESC, o.nombre ASC',
            self::SELECT_CON_CONTEO,
            'oficinas o LEFT JOIN oficinas pa ON pa.id = o.padre_id'
        );
    }

    /** @return list<array{id: int, padre_id: int|null, nombre: string, siglas: string|null, activo: int}> */
    public function listaBasica(): array
    {
        return $this->db->run(
            'SELECT id, padre_id, nombre, siglas, activo FROM oficinas ORDER BY nombre ASC'
        )->fetchAll();
    }

    /** ¿Existe otra oficina con el mismo nombre bajo el mismo padre? */
    public function existeNombreEnPadre(string $nombre, ?int $padreId, ?int $excluirId): bool
    {
        $sql = 'SELECT 1 FROM oficinas WHERE nombre = :nombre AND padre_id <=> :padre';
        $params = ['nombre' => $nombre, 'padre' => $padreId];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $params['excluir'] = $excluirId;
        }

        return $this->db->run($sql . ' LIMIT 1', $params)->fetchColumn() !== false;
    }

    /** @return array{personal: int, equipos: int, hijas: int} */
    public function dependenciasActivas(int $id): array
    {
        $fila = $this->db->run(
            "SELECT
                (SELECT COUNT(*) FROM personal WHERE oficina_id = :id1 AND activo = 1) AS personal,
                (SELECT COUNT(*) FROM equipos WHERE oficina_id = :id2 AND estado_operativo <> 'DE_BAJA') AS equipos,
                (SELECT COUNT(*) FROM oficinas WHERE padre_id = :id3 AND activo = 1) AS hijas",
            ['id1' => $id, 'id2' => $id, 'id3' => $id]
        )->fetch();

        return [
            'personal' => (int) $fila['personal'],
            'equipos'  => (int) $fila['equipos'],
            'hijas'    => (int) $fila['hijas'],
        ];
    }
}
