<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Detalle de un acta: checklist, componentes y software. Las tres tablas son
 * hijas puramente dependientes (ON DELETE CASCADE) y se reemplazan completas
 * cada vez que se guarda el borrador.
 */
final class MantenimientoDetalleModel extends Model
{
    public const COMPONENTES = ['HDD', 'SSD', 'RAM', 'FUENTE', 'PROCESADOR'];
    public const ACCIONES = ['REEMPLAZO', 'INSTALACION', 'NO_APLICA'];

    protected string $table = 'mantenimiento_checklist';

    protected array $fillable = [
        'mantenimiento_id',
        'checklist_item_id',
        'realizado',
        'observacion',
    ];

    /**
     * Checklist guardado del acta, con el texto del ítem, agrupado por categoría.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function checklist(int $mantenimientoId): array
    {
        $filas = $this->db->run(
            "SELECT mc.checklist_item_id, mc.realizado, mc.observacion, ci.codigo, ci.categoria, ci.descripcion
               FROM mantenimiento_checklist mc
               INNER JOIN checklist_items ci ON ci.id = mc.checklist_item_id
              WHERE mc.mantenimiento_id = :id
              ORDER BY FIELD(ci.categoria, 'FISICO', 'LOGICO', 'RED'), ci.orden, ci.descripcion",
            ['id' => $mantenimientoId]
        )->fetchAll();

        $grupos = array_fill_keys(ChecklistItemModel::CATEGORIAS, []);
        foreach ($filas as $fila) {
            $grupos[$fila['categoria']][] = $fila;
        }

        return $grupos;
    }

    /** @return array<string, array{accion: string, detalle: ?string}> componente => datos */
    public function componentes(int $mantenimientoId): array
    {
        $filas = $this->db->run(
            'SELECT componente, accion, detalle FROM mantenimiento_componentes WHERE mantenimiento_id = :id',
            ['id' => $mantenimientoId]
        )->fetchAll();

        $mapa = [];
        foreach (self::COMPONENTES as $c) {
            $mapa[$c] = ['accion' => 'NO_APLICA', 'detalle' => null];
        }
        foreach ($filas as $fila) {
            $mapa[$fila['componente']] = ['accion' => $fila['accion'], 'detalle' => $fila['detalle']];
        }

        return $mapa;
    }

    /** @return list<array{software_id: int, nombre: string, version: ?string}> */
    public function software(int $mantenimientoId): array
    {
        return $this->db->run(
            'SELECT ms.software_id, cs.nombre, ms.version
               FROM mantenimiento_software ms
               INNER JOIN catalogo_software cs ON cs.id = ms.software_id
              WHERE ms.mantenimiento_id = :id
              ORDER BY cs.orden, cs.nombre',
            ['id' => $mantenimientoId]
        )->fetchAll();
    }

    /**
     * @param list<array{checklist_item_id: int, realizado: int}> $checklist
     * @param array<string, array{accion: string, detalle: ?string}> $componentes
     * @param list<array{software_id: int, version: ?string}> $software
     */
    public function reemplazar(int $mantenimientoId, array $checklist, array $componentes, array $software): void
    {
        $params = ['id' => $mantenimientoId];
        $this->db->run('DELETE FROM mantenimiento_checklist WHERE mantenimiento_id = :id', $params);
        $this->db->run('DELETE FROM mantenimiento_componentes WHERE mantenimiento_id = :id', $params);
        $this->db->run('DELETE FROM mantenimiento_software WHERE mantenimiento_id = :id', $params);

        foreach ($checklist as $c) {
            $this->db->run(
                'INSERT INTO mantenimiento_checklist (mantenimiento_id, checklist_item_id, realizado) VALUES (:m, :i, :r)',
                ['m' => $mantenimientoId, 'i' => $c['checklist_item_id'], 'r' => $c['realizado']]
            );
        }
        foreach ($componentes as $componente => $c) {
            $this->db->run(
                'INSERT INTO mantenimiento_componentes (mantenimiento_id, componente, accion, detalle) VALUES (:m, :c, :a, :d)',
                ['m' => $mantenimientoId, 'c' => $componente, 'a' => $c['accion'], 'd' => $c['detalle']]
            );
        }
        foreach ($software as $s) {
            $this->db->run(
                'INSERT INTO mantenimiento_software (mantenimiento_id, software_id, version) VALUES (:m, :s, :v)',
                ['m' => $mantenimientoId, 's' => $s['software_id'], 'v' => $s['version']]
            );
        }
    }
}
