<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Cuentas Microsoft 365 (tabla licencias_office). Cada cuenta permite instalar
 * Office en hasta 5 dispositivos (slots de licencia_equipos).
 */
final class LicenciaModel extends Model
{
    public const MAX_SLOTS = 5;

    protected string $table = 'licencias_office';

    protected array $fillable = [
        'codigo',
        'correo',
        'plan',
        'contrasena_cifrada',
        'fecha_alta',
        'fecha_vencimiento',
        'estado',
        'max_instalaciones',
        'observaciones',
        'activo',
        'created_by',
    ];

    /** Instalaciones vigentes de la cuenta "l" (subconsulta reutilizable). */
    private const SQL_OCUPADOS = '(SELECT COUNT(*) FROM licencia_equipos x WHERE x.licencia_id = l.id AND x.fecha_liberacion IS NULL)';

    /**
     * Listado con el uso de cada cuenta.
     *
     * $estado: '' = todas las activas en el sistema, un valor de ESTADOS, o 'DESACTIVADAS'.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listado(string $texto, string $estado, ?int $oficinaId, bool $soloConCupo, int $page, int $perPage = 20): array
    {
        $condiciones = [];
        $params = [];
        if ($estado === 'DESACTIVADAS') {
            $condiciones[] = 'l.activo = 0';
        } else {
            $condiciones[] = 'l.activo = 1';
            if ($estado !== '') {
                $condiciones[] = 'l.estado = :estado';
                $params['estado'] = $estado;
            }
        }
        if ($texto !== '') {
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $condiciones[] = "(l.codigo LIKE :q1 OR l.correo LIKE :q2 OR EXISTS (
                SELECT 1 FROM licencia_equipos s
                  LEFT JOIN personal p ON p.id = s.personal_id
                  LEFT JOIN equipos e ON e.id = s.equipo_id
                 WHERE s.licencia_id = l.id AND s.fecha_liberacion IS NULL
                   AND (CONCAT(p.nombres, ' ', p.apellidos) LIKE :q3 OR s.usuario_texto LIKE :q4
                        OR e.hostname LIKE :q5 OR e.nro_serie LIKE :q6 OR s.equipo_texto LIKE :q7)))";
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like, 'q6' => $like, 'q7' => $like];
        }
        if ($oficinaId !== null) {
            $condiciones[] = 'EXISTS (SELECT 1 FROM licencia_equipos s2 WHERE s2.licencia_id = l.id AND s2.fecha_liberacion IS NULL AND s2.oficina_id = :oficina)';
            $params['oficina'] = $oficinaId;
        }
        if ($soloConCupo) {
            $condiciones[] = "l.estado = 'ACTIVA' AND " . self::SQL_OCUPADOS . ' < LEAST(l.max_instalaciones, ' . self::MAX_SLOTS . ')';
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'l.activo DESC, l.codigo ASC',
            'l.id, l.codigo, l.correo, l.plan, l.estado, l.fecha_vencimiento, l.max_instalaciones, l.activo,
             ' . self::SQL_OCUPADOS . ' AS ocupados,
             (SELECT COUNT(*) FROM licencia_equipos y WHERE y.licencia_id = l.id AND y.fecha_liberacion IS NULL AND y.estado_verificacion = \'POR_VERIFICAR\') AS por_verificar,
             (SELECT COUNT(*) FROM licencia_equipos z WHERE z.licencia_id = l.id AND z.fecha_liberacion IS NOT NULL) AS liberados',
            'licencias_office l'
        );
    }

    /**
     * Totales de cuentas activas (sin las dadas de baja) para el módulo y el dashboard.
     *
     * @return array{cuentas: int, ocupados: int, capacidad: int, disponibles: int, llenas: int, por_verificar: int}
     */
    public function totales(): array
    {
        $fila = $this->db->run(
            "SELECT COUNT(*) AS cuentas,
                    COALESCE(SUM(t.cap), 0) AS capacidad,
                    COALESCE(SUM(t.ocupados), 0) AS ocupados,
                    COALESCE(SUM(t.ocupados >= t.cap), 0) AS llenas,
                    COALESCE(SUM(t.por_verificar), 0) AS por_verificar
               FROM (SELECT LEAST(l.max_instalaciones, " . self::MAX_SLOTS . ') AS cap,
                            ' . self::SQL_OCUPADOS . " AS ocupados,
                            (SELECT COUNT(*) FROM licencia_equipos v WHERE v.licencia_id = l.id AND v.fecha_liberacion IS NULL
                                AND v.estado_verificacion = 'POR_VERIFICAR') AS por_verificar
                       FROM licencias_office l
                      WHERE l.activo = 1 AND l.estado <> 'BAJA') t"
        )->fetch();

        $capacidad = (int) $fila['capacidad'];
        $ocupados = (int) $fila['ocupados'];

        return [
            'cuentas'       => (int) $fila['cuentas'],
            'ocupados'      => $ocupados,
            'capacidad'     => $capacidad,
            'disponibles'   => max(0, $capacidad - $ocupados),
            'llenas'        => (int) $fila['llenas'],
            'por_verificar' => (int) $fila['por_verificar'],
        ];
    }

    public function contarSlotsActivos(int $licenciaId): int
    {
        return (int) $this->db->run(
            'SELECT COUNT(*) FROM licencia_equipos WHERE licencia_id = :id AND fecha_liberacion IS NULL',
            ['id' => $licenciaId]
        )->fetchColumn();
    }
}
