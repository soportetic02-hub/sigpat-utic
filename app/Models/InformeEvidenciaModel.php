<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Evidencias fotográficas de los informes técnicos (archivos en storage/evidencias).
 */
final class InformeEvidenciaModel extends Model
{
    protected string $table = 'informe_evidencias';

    protected array $fillable = [
        'informe_id',
        'equipo_id',
        'archivo',
        'nombre_original',
        'mime',
        'tamanio_bytes',
        'descripcion',
        'orden',
        'subido_por',
    ];

    /** @return list<array<string, mixed>> */
    public function porInforme(int $informeId): array
    {
        return $this->db->run(
            "SELECT ev.id, ev.equipo_id, ev.archivo, ev.nombre_original, ev.mime, ev.tamanio_bytes, ev.descripcion, ev.orden,
                    e.tipo, e.marca, e.modelo, e.nro_serie
               FROM informe_evidencias ev
               LEFT JOIN equipos e ON e.id = ev.equipo_id
              WHERE ev.informe_id = :informe
              ORDER BY ev.orden, ev.id",
            ['informe' => $informeId]
        )->fetchAll();
    }

    public function contar(int $informeId): int
    {
        return (int) $this->db->run(
            'SELECT COUNT(*) FROM informe_evidencias WHERE informe_id = :informe',
            ['informe' => $informeId]
        )->fetchColumn();
    }

    public function siguienteOrden(int $informeId): int
    {
        return (int) $this->db->run(
            'SELECT COALESCE(MAX(orden), 0) + 1 FROM informe_evidencias WHERE informe_id = :informe',
            ['informe' => $informeId]
        )->fetchColumn();
    }

    /** Desasocia las fotos de equipos que ya no forman parte del informe. */
    public function desasociarEquiposAusentes(int $informeId): void
    {
        $this->db->run(
            'UPDATE informe_evidencias ev
                SET ev.equipo_id = NULL
              WHERE ev.informe_id = :informe AND ev.equipo_id IS NOT NULL
                AND NOT EXISTS (SELECT 1 FROM informe_equipos ie WHERE ie.informe_id = ev.informe_id AND ie.equipo_id = ev.equipo_id)',
            ['informe' => $informeId]
        );
    }

    public function eliminar(int $id): void
    {
        $this->db->run('DELETE FROM informe_evidencias WHERE id = :id', ['id' => $id]);
    }
}
