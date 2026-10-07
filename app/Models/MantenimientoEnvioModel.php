<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Envíos de actas por correo y su confirmación de recepción.
 */
final class MantenimientoEnvioModel extends Model
{
    protected string $table = 'mantenimiento_envios';

    protected array $fillable = [
        'mantenimiento_id',
        'destinatario',
        'copia',
        'token_hash',
        'estado',
        'adjunto_firmado',
        'error',
        'enviado_por',
        'enviado_at',
        'expira_at',
        'visto_at',
        'recibido_at',
        'recibido_ip',
        'recibido_user_agent',
    ];

    /**
     * Historial de envíos de un acta (el más reciente primero).
     *
     * @return list<array<string, mixed>>
     */
    public function deActa(int $mantenimientoId): array
    {
        return $this->db->run(
            "SELECT me.id, me.destinatario, me.copia, me.estado, me.adjunto_firmado, me.error, me.enviado_at, me.expira_at,
                    me.visto_at, me.recibido_at, me.recibido_ip,
                    CONCAT(u.nombres, ' ', u.apellidos) AS enviado_por_nombre
               FROM mantenimiento_envios me
               INNER JOIN usuarios_sistema u ON u.id = me.enviado_por
              WHERE me.mantenimiento_id = :acta
              ORDER BY me.id DESC",
            ['acta' => $mantenimientoId]
        )->fetchAll();
    }

    /**
     * Envío por el hash de su token, con los datos del acta para la página pública.
     *
     * @return array<string, mixed>|null
     */
    public function porToken(string $tokenHash, bool $bloquear = false): ?array
    {
        $fila = $this->db->run(
            "SELECT me.*,
                    m.numero, m.tipo, m.fecha_ingreso, m.fecha_salida, m.firmado_at,
                    e.tipo AS equipo_tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial,
                    o.nombre AS oficina_nombre,
                    CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre,
                    CONCAT(u.nombres, ' ', u.apellidos) AS tecnico_nombre
               FROM mantenimiento_envios me
               INNER JOIN mantenimientos m ON m.id = me.mantenimiento_id
               INNER JOIN equipos e ON e.id = m.equipo_id
               INNER JOIN oficinas o ON o.id = m.oficina_id
               LEFT JOIN personal p ON p.id = m.personal_id
               INNER JOIN usuarios_sistema u ON u.id = m.tecnico_id
              WHERE me.token_hash = :hash
              LIMIT 1" . ($bloquear ? ' FOR UPDATE' : ''),
            ['hash' => $tokenHash]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /** Borra todos los envíos de un acta (solo al eliminar el acta). */
    public function eliminarDeActa(int $mantenimientoId): void
    {
        $this->db->run('DELETE FROM mantenimiento_envios WHERE mantenimiento_id = :acta', ['acta' => $mantenimientoId]);
    }

    /** Registra la primera apertura del enlace (no cambia el estado). */
    public function marcarVisto(int $id): void
    {
        $this->db->run(
            'UPDATE mantenimiento_envios SET visto_at = NOW() WHERE id = :id AND visto_at IS NULL',
            ['id' => $id]
        );
    }
}
