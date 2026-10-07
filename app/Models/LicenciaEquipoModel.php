<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Instalaciones (slots 1-5) de las cuentas Microsoft 365. Liberar un slot nunca borra la fila.
 *
 * Un slot identifica el equipo por equipo_id (inventario) o equipo_texto (tablet, equipo no
 * inventariado), la persona por personal_id o usuario_texto, y la oficina por oficina_id.
 */
final class LicenciaEquipoModel extends Model
{
    protected string $table = 'licencia_equipos';

    protected array $fillable = [
        'licencia_id',
        'equipo_id',
        'equipo_texto',
        'personal_id',
        'usuario_texto',
        'oficina_id',
        'estado_verificacion',
        'slot',
        'fecha_asignacion',
        'asignado_por',
        'fecha_liberacion',
        'motivo_liberacion',
        'liberado_por',
        'observacion',
    ];

    /** Columnas comunes de un slot con su equipo, persona y oficina (alias le, e, p, o). */
    private const SQL_DETALLE = "le.id, le.slot, le.fecha_asignacion, le.observacion, le.estado_verificacion,
        le.equipo_id, le.equipo_texto, e.tipo, e.marca, e.modelo, e.nro_serie, e.hostname, e.ip_lan, e.estado_operativo,
        le.personal_id, le.usuario_texto,
        CASE WHEN p.id IS NULL THEN le.usuario_texto ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS persona_nombre,
        le.oficina_id, o.nombre AS oficina_nombre, o.siglas AS oficina_siglas";

    private const SQL_JOINS = 'LEFT JOIN equipos e ON e.id = le.equipo_id
        LEFT JOIN personal p ON p.id = le.personal_id
        LEFT JOIN oficinas o ON o.id = le.oficina_id';

    /**
     * Instalación vigente de un equipo del inventario, con datos de la cuenta.
     *
     * @return array<string, mixed>|null
     */
    public function activoDeEquipo(int $equipoId, bool $bloquear = false): ?array
    {
        $fila = $this->db->run(
            'SELECT le.id, le.licencia_id, le.equipo_id, le.slot, le.fecha_asignacion, le.estado_verificacion,
                    l.codigo, l.correo, l.plan
               FROM licencia_equipos le
               INNER JOIN licencias_office l ON l.id = le.licencia_id
              WHERE le.equipo_activo = :equipo' . ($bloquear ? ' FOR UPDATE' : ''),
            ['equipo' => $equipoId]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /** Registra la liberación de un slot (conserva la fila como historial). */
    public function liberar(int $id, string $motivo, int $usuarioId, ?string $observacion = null): void
    {
        $this->db->run(
            'UPDATE licencia_equipos
                SET fecha_liberacion = NOW(), motivo_liberacion = :motivo, liberado_por = :usuario,
                    observacion = COALESCE(:observacion, observacion)
              WHERE id = :id AND fecha_liberacion IS NULL',
            ['motivo' => $motivo, 'usuario' => $usuarioId, 'observacion' => $observacion, 'id' => $id]
        );
    }

    /**
     * Números de slot ocupados de una cuenta (bloqueados con FOR UPDATE si se pide).
     *
     * @return list<int>
     */
    public function slotsOcupados(int $licenciaId, bool $bloquear = false): array
    {
        $filas = $this->db->run(
            'SELECT slot FROM licencia_equipos WHERE licencia_id = :id AND fecha_liberacion IS NULL ORDER BY slot'
            . ($bloquear ? ' FOR UPDATE' : ''),
            ['id' => $licenciaId]
        )->fetchAll();

        return array_map(static fn (array $f): int => (int) $f['slot'], $filas);
    }

    /**
     * Slots vigentes de una cuenta.
     *
     * @return list<array<string, mixed>>
     */
    public function activosDeLicencia(int $licenciaId): array
    {
        return $this->db->run(
            'SELECT ' . self::SQL_DETALLE . ", CONCAT(ua.nombres, ' ', ua.apellidos) AS asignado_por_nombre
               FROM licencia_equipos le
               " . self::SQL_JOINS . '
               LEFT JOIN usuarios_sistema ua ON ua.id = le.asignado_por
              WHERE le.licencia_id = :id AND le.fecha_liberacion IS NULL
              ORDER BY le.slot',
            ['id' => $licenciaId]
        )->fetchAll();
    }

    /**
     * Historial de instalaciones liberadas de una cuenta.
     *
     * @return list<array<string, mixed>>
     */
    public function liberadosDeLicencia(int $licenciaId): array
    {
        return $this->db->run(
            'SELECT ' . self::SQL_DETALLE . ", le.fecha_liberacion, le.motivo_liberacion,
                    CONCAT(ul.nombres, ' ', ul.apellidos) AS liberado_por_nombre
               FROM licencia_equipos le
               " . self::SQL_JOINS . '
               LEFT JOIN usuarios_sistema ul ON ul.id = le.liberado_por
              WHERE le.licencia_id = :id AND le.fecha_liberacion IS NOT NULL
              ORDER BY le.fecha_liberacion DESC, le.id DESC',
            ['id' => $licenciaId]
        )->fetchAll();
    }

    /**
     * Equipos del inventario que pueden recibir una instalación: PC o LAPTOP, no dados de
     * baja y sin otra licencia vigente. Trae la oficina y el responsable para precargarlos.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarElegibles(string $texto, int $limite = 15): array
    {
        $like = '%' . addcslashes($texto, '%_\\') . '%';

        return $this->db->run(
            "SELECT e.id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.hostname, e.ip_lan,
                    e.oficina_id, o.nombre AS oficina_nombre,
                    e.personal_id, CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre
               FROM equipos e
               INNER JOIN oficinas o ON o.id = e.oficina_id
               LEFT JOIN personal p ON p.id = e.personal_id
              WHERE e.tipo IN ('PC', 'LAPTOP')
                AND e.estado_operativo <> 'DE_BAJA'
                AND NOT EXISTS (SELECT 1 FROM licencia_equipos le WHERE le.equipo_activo = e.id)
                AND (e.nro_serie LIKE :q1 OR e.codigo_patrimonial LIKE :q2 OR e.hostname LIKE :q3 OR e.ip_lan LIKE :q4
                     OR CONCAT(e.marca, ' ', e.modelo) LIKE :q5 OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q6)
              ORDER BY e.hostname IS NULL, e.hostname, e.tipo, e.marca, e.modelo
              LIMIT :limite",
            ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like, 'q6' => $like, 'limite' => $limite]
        )->fetchAll();
    }

    public function tuvoLicencias(int $equipoId): bool
    {
        return $this->db->run(
            'SELECT 1 FROM licencia_equipos WHERE equipo_id = :equipo LIMIT 1',
            ['equipo' => $equipoId]
        )->fetchColumn() !== false;
    }
}
