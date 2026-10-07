<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Informes técnicos (BORRADOR -> EMITIDO).
 */
final class InformeModel extends Model
{
    public const ACCIONES = ['REPARACION', 'INOPERATIVIDAD', 'BAJA_DEFINITIVA', 'REEMPLAZO'];
    public const ESTADOS = ['BORRADOR', 'EMITIDO'];

    protected string $table = 'informes_tecnicos';

    protected array $fillable = [
        'numero',
        'anio',
        'correlativo',
        'para_nombre',
        'para_cargo',
        'de_usuario_id',
        'de_nombre',
        'de_cargo',
        'asunto',
        'fecha',
        'antecedentes',
        'accion_requerida',
        'conclusiones',
        'recomendaciones',
        'estado',
        'emitido_por',
        'emitido_at',
        'pdf_ruta',
        'docx_ruta',
        'created_by',
    ];

    /**
     * @param array{desde?: ?string, hasta?: ?string, accion?: ?string, estado?: ?string, q?: string} $f
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listado(array $f, int $page, int $perPage = 20): array
    {
        $condiciones = [];
        $params = [];
        if (!empty($f['desde'])) {
            $condiciones[] = 'i.fecha >= :desde';
            $params['desde'] = $f['desde'];
        }
        if (!empty($f['hasta'])) {
            $condiciones[] = 'i.fecha <= :hasta';
            $params['hasta'] = $f['hasta'];
        }
        if (!empty($f['accion'])) {
            $condiciones[] = 'i.accion_requerida = :accion';
            $params['accion'] = $f['accion'];
        }
        if (!empty($f['estado'])) {
            $condiciones[] = 'i.estado = :estado';
            $params['estado'] = $f['estado'];
        }
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $condiciones[] = '(i.numero LIKE :q1 OR i.asunto LIKE :q2 OR EXISTS (
                SELECT 1 FROM informe_equipos ie INNER JOIN equipos e ON e.id = ie.equipo_id
                 WHERE ie.informe_id = i.id AND (e.nro_serie LIKE :q3 OR e.codigo_patrimonial LIKE :q4)))';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'i.fecha DESC, i.id DESC',
            'i.id, i.numero, i.asunto, i.fecha, i.accion_requerida, i.estado, i.de_nombre,
             (SELECT COUNT(*) FROM informe_equipos ie WHERE ie.informe_id = i.id) AS total_equipos,
             (SELECT COUNT(*) FROM informe_evidencias ev WHERE ev.informe_id = i.id) AS total_evidencias',
            'informes_tecnicos i'
        );
    }

    /** @return array<string, mixed>|null */
    public function detalle(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT i.*, CONCAT(ue.nombres, ' ', ue.apellidos) AS emitido_por_nombre, u.dni AS de_dni
               FROM informes_tecnicos i
               INNER JOIN usuarios_sistema u ON u.id = i.de_usuario_id
               LEFT JOIN usuarios_sistema ue ON ue.id = i.emitido_por
              WHERE i.id = :id",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    public function eliminarBorrador(int $id): void
    {
        $this->db->run("DELETE FROM informes_tecnicos WHERE id = :id AND estado = 'BORRADOR'", ['id' => $id]);
    }

    /** Marca recomendado_baja = 1 en los equipos del informe que no estén ya dados de baja. */
    public function marcarRecomendadoBaja(int $informeId, int $usuarioId): int
    {
        return $this->db->run(
            "UPDATE equipos e
               INNER JOIN informe_equipos ie ON ie.equipo_id = e.id
                SET e.recomendado_baja = 1, e.updated_by = :usuario
              WHERE ie.informe_id = :informe AND e.estado_operativo <> 'DE_BAJA'",
            ['usuario' => $usuarioId, 'informe' => $informeId]
        )->rowCount();
    }
}
