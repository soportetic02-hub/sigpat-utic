<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Actas de mantenimiento (BORRADOR -> CERRADA).
 */
final class MantenimientoModel extends Model
{
    public const TIPOS = ['PREVENTIVO', 'CORRECTIVO'];
    public const ESTADOS = ['BORRADOR', 'CERRADA'];
    public const ESTADOS_ENVIO = ['NO_ENVIADO', 'ENVIADO', 'RECIBIDO', 'ERROR'];
    public const FIRMA_FIRMADA = 'FIRMADA';
    public const FIRMA_PENDIENTE = 'PENDIENTE';

    protected string $table = 'mantenimientos';

    protected array $fillable = [
        'numero',
        'anio',
        'correlativo',
        'equipo_id',
        'personal_id',
        'oficina_id',
        'tecnico_id',
        'tipo',
        'estado',
        'fecha_ingreso',
        'fecha_salida',
        'problema_reportado',
        'observaciones',
        'recomendaciones',
        'estado_equipo_previo',
        'estado_equipo_final',
        'condicion_final',
        'pdf_ruta',
        'pdf_firmado_ruta',
        'firmado_por',
        'firmado_at',
        'firma_titular',
        'firma_dni',
        'firma_sha256',
        'estado_envio',
        'cerrado_por',
        'cerrado_at',
        'created_by',
    ];

    /**
     * Listado con filtros por fecha de ingreso, técnico, oficina, tipo y estado.
     *
     * @param array{desde?: ?string, hasta?: ?string, tecnico_id?: ?int, oficina_id?: ?int, tipo?: ?string, estado?: ?string, firma?: ?string, envio?: ?string, q?: string} $f
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listado(array $f, int $page, int $perPage = 20): array
    {
        $condiciones = [];
        $params = [];
        if (!empty($f['desde'])) {
            $condiciones[] = 'm.fecha_ingreso >= :desde';
            $params['desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $condiciones[] = 'm.fecha_ingreso <= :hasta';
            $params['hasta'] = $f['hasta'] . ' 23:59:59';
        }
        if (!empty($f['tecnico_id'])) {
            $condiciones[] = 'm.tecnico_id = :tecnico';
            $params['tecnico'] = (int) $f['tecnico_id'];
        }
        if (!empty($f['oficina_id'])) {
            $condiciones[] = 'm.oficina_id = :oficina';
            $params['oficina'] = (int) $f['oficina_id'];
        }
        if (!empty($f['tipo'])) {
            $condiciones[] = 'm.tipo = :tipo';
            $params['tipo'] = $f['tipo'];
        }
        if (!empty($f['estado'])) {
            $condiciones[] = 'm.estado = :estado';
            $params['estado'] = $f['estado'];
        }
        if (($f['firma'] ?? null) === self::FIRMA_FIRMADA) {
            $condiciones[] = 'm.pdf_firmado_ruta IS NOT NULL';
        } elseif (($f['firma'] ?? null) === self::FIRMA_PENDIENTE) {
            $condiciones[] = "m.estado = 'CERRADA' AND m.pdf_firmado_ruta IS NULL";
        }
        if (!empty($f['envio'])) {
            $condiciones[] = "m.estado = 'CERRADA' AND m.estado_envio = :envio";
            $params['envio'] = $f['envio'];
        }
        $q =trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $condiciones[] = "(m.numero LIKE :q1 OR e.nro_serie LIKE :q2 OR e.codigo_patrimonial LIKE :q3 OR e.hostname LIKE :q4
                               OR CONCAT(p.nombres, ' ', p.apellidos) LIKE :q5)";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'm.fecha_ingreso DESC, m.id DESC',
            "m.id, m.numero, m.tipo, m.estado, m.fecha_ingreso, m.fecha_salida, m.estado_equipo_final,
             m.firmado_at, m.estado_envio,
             e.id AS equipo_id, e.tipo AS equipo_tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial,
             o.nombre AS oficina_nombre, o.siglas AS oficina_siglas,
             CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre,
             CONCAT(u.nombres, ' ', u.apellidos) AS tecnico_nombre",
            'mantenimientos m
             INNER JOIN equipos e ON e.id = m.equipo_id
             INNER JOIN oficinas o ON o.id = m.oficina_id
             LEFT JOIN personal p ON p.id = m.personal_id
             INNER JOIN usuarios_sistema u ON u.id = m.tecnico_id'
        );
    }

    /**
     * Acta con todos los datos para la vista y el PDF (equipo, snapshot de
     * personal/oficina, técnico).
     *
     * @return array<string, mixed>|null
     */
    public function detalle(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT m.*,
                    e.tipo AS equipo_tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.codigo_interno,
                    e.procesador, e.ram_gb, e.disco_tipo, e.disco_capacidad_gb, e.sistema_operativo,
                    e.hostname, e.ip_lan, e.mac_lan, e.mac_wifi, e.estado_operativo AS equipo_estado_actual,
                    o.nombre AS oficina_nombre, o.siglas AS oficina_siglas, o.ubicacion AS oficina_ubicacion,
                    op.nombre AS dependencia_nombre, op.siglas AS dependencia_siglas,
                    (SELECT c.anio FROM equipo_codigos c WHERE c.equipo_id = e.id ORDER BY c.anio DESC LIMIT 1) AS codigo_anual_anio,
                    (SELECT c.codigo FROM equipo_codigos c WHERE c.equipo_id = e.id ORDER BY c.anio DESC LIMIT 1) AS codigo_anual,
                    p.nombres AS personal_nombres, p.apellidos AS personal_apellidos, p.dni AS personal_dni,
                    p.cargo AS personal_cargo, p.email AS personal_email,
                    u.nombres AS tecnico_nombres, u.apellidos AS tecnico_apellidos, u.dni AS tecnico_dni,
                    u.cargo AS tecnico_cargo,
                    CONCAT(uc.nombres, ' ', uc.apellidos) AS cerrado_por_nombre,
                    CONCAT(uf.nombres, ' ', uf.apellidos) AS firmado_por_nombre
               FROM mantenimientos m
               INNER JOIN equipos e ON e.id = m.equipo_id
               INNER JOIN oficinas o ON o.id = m.oficina_id
               LEFT JOIN oficinas op ON op.id = o.padre_id
               LEFT JOIN personal p ON p.id = m.personal_id
               INNER JOIN usuarios_sistema u ON u.id = m.tecnico_id
               LEFT JOIN usuarios_sistema uc ON uc.id = m.cerrado_por
               LEFT JOIN usuarios_sistema uf ON uf.id = m.firmado_por
              WHERE m.id = :id",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /** Acta en BORRADOR del equipo, si existe. */
    public function borradorDeEquipo(int $equipoId): ?int
    {
        $id = $this->db->run(
            "SELECT id FROM mantenimientos WHERE equipo_id = :equipo AND estado = 'BORRADOR' LIMIT 1",
            ['equipo' => $equipoId]
        )->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** @return list<array{id: int, nombre: string}> */
    public function tecnicosConActas(): array
    {
        return $this->db->run(
            "SELECT DISTINCT u.id, CONCAT(u.apellidos, ', ', u.nombres) AS nombre
               FROM mantenimientos m INNER JOIN usuarios_sistema u ON u.id = m.tecnico_id
              ORDER BY nombre"
        )->fetchAll();
    }

    public function eliminarBorrador(int $id): void
    {
        $this->db->run("DELETE FROM mantenimientos WHERE id = :id AND estado = 'BORRADOR'", ['id' => $id]);
    }

    /**
     * Elimina un acta CERRADA cuya recepción no fue confirmada. Checklist, componentes y
     * software se borran en cascada; los envíos deben borrarse antes (FK RESTRICT).
     */
    public function eliminarCerradaNoRecibida(int $id): void
    {
        $this->db->run(
            "DELETE FROM mantenimientos WHERE id = :id AND estado = 'CERRADA' AND estado_envio <> 'RECIBIDO'",
            ['id' => $id]
        );
    }
}
