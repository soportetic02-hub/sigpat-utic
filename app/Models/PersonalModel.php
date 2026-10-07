<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Personal institucional (usa equipos, no inicia sesión).
 */
final class PersonalModel extends Model
{
    protected string $table = 'personal';

    protected array $fillable = [
        'nombres',
        'apellidos',
        'dni',
        'cargo',
        'email',
        'telefono',
        'oficina_id',
        'activo',
    ];

    /**
     * Listado paginado con búsqueda y filtros.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function buscar(string $texto, ?int $oficinaId, ?int $activo, int $page, int $perPage = 15): array
    {
        $condiciones = [];
        $params = [];
        if ($texto !== '') {
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $condiciones[] = "(p.nombres LIKE :q1 OR p.apellidos LIKE :q2 OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q3
                               OR p.dni LIKE :q4 OR p.email LIKE :q5 OR p.cargo LIKE :q6)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like, 'q6' => $like];
        }
        if ($oficinaId !== null) {
            $condiciones[] = 'p.oficina_id = :oficina';
            $params['oficina'] = $oficinaId;
        }
        if ($activo !== null) {
            $condiciones[] = 'p.activo = :activo';
            $params['activo'] = $activo;
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'p.activo DESC, p.apellidos ASC, p.nombres ASC',
            "p.id, p.nombres, p.apellidos, p.dni, p.cargo, p.email, p.telefono, p.oficina_id, p.activo,
             o.nombre AS oficina_nombre, o.siglas AS oficina_siglas,
             (SELECT COUNT(*) FROM equipos e WHERE e.personal_id = p.id) AS total_equipos",
            'personal p INNER JOIN oficinas o ON o.id = p.oficina_id'
        );
    }

    /** @return array<string, mixed>|null */
    public function detalle(int $id): ?array
    {
        $fila = $this->db->run(
            'SELECT p.*, o.nombre AS oficina_nombre, o.siglas AS oficina_siglas
               FROM personal p
               INNER JOIN oficinas o ON o.id = p.oficina_id
              WHERE p.id = :id',
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Personal activo para un <select> agrupado por oficina.
     *
     * @return list<array{id: int, nombre: string, oficina_id: int, oficina_nombre: string}>
     */
    public function activosParaSelect(?int $incluirId = null): array
    {
        return $this->db->run(
            "SELECT p.id, CONCAT(p.apellidos, ', ', p.nombres) AS nombre, p.oficina_id, o.nombre AS oficina_nombre
               FROM personal p
               INNER JOIN oficinas o ON o.id = p.oficina_id
              WHERE p.activo = 1 OR p.id = :incluir
              ORDER BY o.nombre, p.apellidos, p.nombres",
            ['incluir' => $incluirId ?? 0]
        )->fetchAll();
    }

    /**
     * Búsqueda rápida para autocompletado (personal activo).
     *
     * @return list<array<string, mixed>>
     */
    public function autocompletar(string $texto, int $limite = 10): array
    {
        $like = '%' . addcslashes($texto, '%_\\') . '%';

        return $this->db->run(
            "SELECT p.id, p.nombres, p.apellidos, p.dni, p.cargo, o.nombre AS oficina_nombre
               FROM personal p
               INNER JOIN oficinas o ON o.id = p.oficina_id
              WHERE p.activo = 1
                AND (CONCAT(p.nombres, ' ', p.apellidos) LIKE :q1 OR CONCAT(p.apellidos, ' ', p.nombres) LIKE :q2 OR p.dni LIKE :q3)
              ORDER BY p.apellidos, p.nombres
              LIMIT :limite",
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'limite' => $limite]
        )->fetchAll();
    }
}
