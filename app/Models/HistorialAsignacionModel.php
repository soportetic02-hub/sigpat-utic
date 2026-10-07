<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Historial de ubicación y responsable de cada equipo.
 * Solo un registro vigente (fecha_fin NULL) por equipo: uq_historial_asignaciones_equipo_vigente.
 */
final class HistorialAsignacionModel extends Model
{
    protected string $table = 'historial_asignaciones';

    protected array $fillable = [
        'equipo_id',
        'oficina_id',
        'personal_id',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'observacion',
        'usuario_id',
    ];

    /** @return array<string, mixed>|null */
    public function vigente(int $equipoId): ?array
    {
        $fila = $this->db->run(
            'SELECT * FROM historial_asignaciones WHERE equipo_id = :equipo AND fecha_fin IS NULL LIMIT 1 FOR UPDATE',
            ['equipo' => $equipoId]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    public function cerrarVigente(int $equipoId): void
    {
        $this->db->run(
            'UPDATE historial_asignaciones SET fecha_fin = NOW() WHERE equipo_id = :equipo AND fecha_fin IS NULL',
            ['equipo' => $equipoId]
        );
    }

    /**
     * Historial en el que participó una persona (como responsable).
     *
     * @return list<array<string, mixed>>
     */
    public function porPersonal(int $personalId, int $limite = 100): array
    {
        return $this->db->run(
            "SELECT h.id, h.fecha_inicio, h.fecha_fin, h.motivo, h.observacion,
                    (SELECT s.motivo FROM historial_asignaciones s
                      WHERE s.equipo_id = h.equipo_id AND s.id > h.id
                      ORDER BY s.id ASC LIMIT 1) AS motivo_cierre,
                    e.id AS equipo_id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial,
                    o.nombre AS oficina_nombre,
                    CONCAT(u.nombres, ' ', u.apellidos) AS registrado_por
               FROM historial_asignaciones h
               INNER JOIN equipos e ON e.id = h.equipo_id
               INNER JOIN oficinas o ON o.id = h.oficina_id
               LEFT JOIN usuarios_sistema u ON u.id = h.usuario_id
              WHERE h.personal_id = :personal
              ORDER BY h.fecha_inicio DESC, h.id DESC
              LIMIT :limite",
            ['personal' => $personalId, 'limite' => $limite]
        )->fetchAll();
    }

    /**
     * Historial completo de un equipo.
     *
     * @return list<array<string, mixed>>
     */
    public function porEquipo(int $equipoId): array
    {
        return $this->db->run(
            "SELECT h.id, h.fecha_inicio, h.fecha_fin, h.motivo, h.observacion,
                    o.nombre AS oficina_nombre, h.personal_id,
                    CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre,
                    CONCAT(u.nombres, ' ', u.apellidos) AS registrado_por
               FROM historial_asignaciones h
               INNER JOIN oficinas o ON o.id = h.oficina_id
               LEFT JOIN personal p ON p.id = h.personal_id
               LEFT JOIN usuarios_sistema u ON u.id = h.usuario_id
              WHERE h.equipo_id = :equipo
              ORDER BY h.fecha_inicio DESC, h.id DESC",
            ['equipo' => $equipoId]
        )->fetchAll();
    }
}
