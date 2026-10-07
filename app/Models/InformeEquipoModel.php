<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Equipos evaluados en cada informe técnico.
 */
final class InformeEquipoModel extends Model
{
    protected string $table = 'informe_equipos';

    protected array $fillable = [
        'informe_id',
        'equipo_id',
        'caracteristicas',
        'estado_funcional',
        'diagnostico',
        'orden',
    ];

    /**
     * Equipos del informe con sus datos de inventario.
     *
     * @return list<array<string, mixed>>
     */
    public function porInforme(int $informeId): array
    {
        return $this->db->run(
            "SELECT ie.id, ie.equipo_id, ie.caracteristicas, ie.estado_funcional, ie.diagnostico, ie.orden,
                    e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.estado_operativo, e.condicion_fisica,
                    e.recomendado_baja, e.fecha_adquisicion, o.nombre AS oficina_nombre,
                    CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre
               FROM informe_equipos ie
               INNER JOIN equipos e ON e.id = ie.equipo_id
               INNER JOIN oficinas o ON o.id = e.oficina_id
               LEFT JOIN personal p ON p.id = e.personal_id
              WHERE ie.informe_id = :informe
              ORDER BY ie.orden, ie.id",
            ['informe' => $informeId]
        )->fetchAll();
    }

    /**
     * Reemplaza los equipos evaluados del informe.
     *
     * @param list<array{equipo_id: int, caracteristicas: ?string, estado_funcional: ?string, diagnostico: ?string}> $filas
     */
    public function reemplazar(int $informeId, array $filas): void
    {
        $this->db->run('DELETE FROM informe_equipos WHERE informe_id = :informe', ['informe' => $informeId]);
        foreach (array_values($filas) as $orden => $f) {
            $this->insert(['informe_id' => $informeId, 'orden' => $orden + 1] + $f);
        }
    }

    /**
     * Equipos que pueden evaluarse (no dados de baja), para el buscador del formulario.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarEvaluables(string $texto, int $limite = 15): array
    {
        $like = '%' . addcslashes($texto, '%_\\') . '%';

        return $this->db->run(
            "SELECT e.id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.hostname, e.estado_operativo,
                    e.condicion_fisica, e.procesador, e.ram_gb, e.disco_tipo, e.disco_capacidad_gb, e.sistema_operativo,
                    e.fecha_adquisicion, o.nombre AS oficina_nombre,
                    CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre
               FROM equipos e
               INNER JOIN oficinas o ON o.id = e.oficina_id
               LEFT JOIN personal p ON p.id = e.personal_id
              WHERE e.estado_operativo <> 'DE_BAJA'
                AND (e.nro_serie LIKE :q1 OR e.codigo_patrimonial LIKE :q2 OR e.hostname LIKE :q3
                     OR CONCAT(e.marca, ' ', e.modelo) LIKE :q4 OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q5)
              ORDER BY e.tipo, e.marca, e.modelo
              LIMIT :limite",
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like, 'limite' => $limite]
        )->fetchAll();
    }

    /**
     * Datos de equipos concretos (precarga desde el dashboard o la ficha del equipo).
     *
     * @param list<int> $ids
     * @return list<array<string, mixed>>
     */
    public function equiposPorIds(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn (int $i): bool => $i > 0)));
        if ($ids === []) {
            return [];
        }
        $params = [];
        foreach ($ids as $i => $id) {
            $params['id' . $i] = $id;
        }

        return $this->db->run(
            "SELECT e.id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.hostname, e.estado_operativo,
                    e.condicion_fisica, e.procesador, e.ram_gb, e.disco_tipo, e.disco_capacidad_gb, e.sistema_operativo,
                    e.fecha_adquisicion, o.nombre AS oficina_nombre,
                    CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre
               FROM equipos e
               INNER JOIN oficinas o ON o.id = e.oficina_id
               LEFT JOIN personal p ON p.id = e.personal_id
              WHERE e.id IN (:" . implode(', :', array_keys($params)) . ')',
            $params
        )->fetchAll();
    }
}
