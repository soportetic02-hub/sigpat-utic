<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Consultas agregadas del dashboard sobre v_equipos_estado (sin equipos DE_BAJA).
 */
final class DashboardModel extends Model
{
    protected string $table = 'equipos';

    /**
     * Todos los contadores de equipos en una sola pasada sobre la vista.
     *
     * @return array<string, int>
     */
    public function contadoresEquipos(): array
    {
        $fila = $this->db->run(
            "SELECT COUNT(*) AS total,
                    COALESCE(SUM(tipo = 'PC'), 0) AS pc,
                    COALESCE(SUM(tipo = 'LAPTOP'), 0) AS laptop,
                    COALESCE(SUM(tipo = 'IMPRESORA'), 0) AS impresora,
                    COALESCE(SUM(estado_operativo = 'EN_MANTENIMIENTO'), 0) AS en_mantenimiento,
                    COALESCE(SUM(fecha_ultimo_mantenimiento IS NULL
                                 OR fecha_proximo_mantenimiento <= CURDATE()), 0) AS pendientes,
                    COALESCE(SUM(fecha_ultimo_mantenimiento IS NULL), 0) AS nunca_mantenidos,
                    COALESCE(SUM(condicion_fisica = 'MALO'
                                 OR recomendado_baja = 1
                                 OR fecha_sugerida_baja <= CURDATE()), 0) AS riesgo,
                    COALESCE(SUM(condicion_fisica = 'MALO'), 0) AS malos,
                    COALESCE(SUM(recomendado_baja = 1), 0) AS recomendados_baja,
                    COALESCE(SUM(fecha_sugerida_baja <= CURDATE()), 0) AS vida_cumplida
               FROM v_equipos_estado
              WHERE estado_operativo <> 'DE_BAJA'"
        )->fetch();

        return array_map('intval', $fila);
    }

    public function actasCerradasEnAnio(int $anio): int
    {
        return (int) $this->db->run(
            "SELECT COUNT(*) FROM mantenimientos
              WHERE estado = 'CERRADA' AND fecha_salida >= :inicio AND fecha_salida < :fin",
            ['inicio' => $anio . '-01-01 00:00:00', 'fin' => ($anio + 1) . '-01-01 00:00:00']
        )->fetchColumn();
    }

    /**
     * Actas cerradas por mes y tipo en un año.
     *
     * @return list<array{mes: int, tipo: string, total: int}>
     */
    public function actasPorMes(int $anio): array
    {
        return $this->db->run(
            "SELECT MONTH(fecha_salida) AS mes, tipo, COUNT(*) AS total
               FROM mantenimientos
              WHERE estado = 'CERRADA' AND fecha_salida >= :inicio AND fecha_salida < :fin
              GROUP BY MONTH(fecha_salida), tipo",
            ['inicio' => $anio . '-01-01 00:00:00', 'fin' => ($anio + 1) . '-01-01 00:00:00']
        )->fetchAll();
    }

    /**
     * Mantenimientos vencidos (próximo mantenimiento <= hoy), los más atrasados primero.
     *
     * @return list<array<string, mixed>>
     */
    public function mantenimientosVencidos(int $limite): array
    {
        return $this->db->run(
            "SELECT id, tipo, marca, modelo, nro_serie, codigo_patrimonial, oficina_nombre, oficina_siglas,
                    personal_nombre, estado_operativo, fecha_ultimo_mantenimiento, fecha_proximo_mantenimiento,
                    dias_retraso_mantenimiento
               FROM v_equipos_estado
              WHERE estado_operativo <> 'DE_BAJA' AND fecha_proximo_mantenimiento <= CURDATE()
              ORDER BY dias_retraso_mantenimiento DESC, id
              LIMIT :limite",
            ['limite' => $limite]
        )->fetchAll();
    }

    public function contarVencidos(): int
    {
        return (int) $this->db->run(
            "SELECT COUNT(*) FROM v_equipos_estado
              WHERE estado_operativo <> 'DE_BAJA' AND fecha_proximo_mantenimiento <= CURDATE()"
        )->fetchColumn();
    }

    /**
     * Equipos al final de su vida útil (fecha sugerida de baja <= hoy), los más antiguos primero.
     *
     * @return list<array<string, mixed>>
     */
    public function finVidaUtil(int $limite): array
    {
        return $this->db->run(
            "SELECT id, tipo, marca, modelo, nro_serie, codigo_patrimonial, oficina_nombre, oficina_siglas,
                    personal_nombre, condicion_fisica, recomendado_baja, fecha_adquisicion, anios_antiguedad,
                    fecha_sugerida_baja
               FROM v_equipos_estado
              WHERE estado_operativo <> 'DE_BAJA' AND fecha_sugerida_baja <= CURDATE()
              ORDER BY fecha_sugerida_baja ASC, id
              LIMIT :limite",
            ['limite' => $limite]
        )->fetchAll();
    }
}
