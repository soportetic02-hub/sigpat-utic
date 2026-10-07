<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DashboardModel;
use App\Models\LicenciaModel;

/**
 * KPIs, series para gráficos y alertas del dashboard.
 * Se excluyen los equipos DE_BAJA.
 */
final class DashboardService
{
    public const MESES = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
    private const LIMITE_ALERTAS = 10;

    private DashboardModel $modelo;

    public function __construct()
    {
        $this->modelo = new DashboardModel();
    }

    /**
     * @return array{
     *   equipos: array{total: int, pc: int, laptop: int, impresora: int},
     *   mantenimientos: array{cerradas: int, pendientes: int, nunca: int, en_curso: int, anio: int},
     *   licencias: array{cuentas: int, ocupados: int, capacidad: int, disponibles: int, llenas: int, por_verificar: int, porcentaje: int},
     *   riesgo: array{total: int, malos: int, recomendados: int, vida_cumplida: int}
     * }
     */
    public function kpis(): array
    {
        $c = $this->modelo->contadoresEquipos();
        $anio = (int) date('Y');
        $lic = (new LicenciaModel())->totales();

        return [
            'equipos' => [
                'total'     => $c['total'],
                'pc'        => $c['pc'],
                'laptop'    => $c['laptop'],
                'impresora' => $c['impresora'],
            ],
            'mantenimientos' => [
                'cerradas'   => $this->modelo->actasCerradasEnAnio($anio),
                'pendientes' => $c['pendientes'],
                'nunca'      => $c['nunca_mantenidos'],
                'en_curso'   => $c['en_mantenimiento'],
                'anio'       => $anio,
            ],
            'licencias' => $lic + [
                'porcentaje' => $lic['capacidad'] > 0 ? (int) round($lic['ocupados'] * 100 / $lic['capacidad']) : 0,
            ],
            'riesgo' => [
                'total'         => $c['riesgo'],
                'malos'         => $c['malos'],
                'recomendados'  => $c['recomendados_baja'],
                'vida_cumplida' => $c['vida_cumplida'],
            ],
        ];
    }

    /**
     * Actas cerradas por mes del año, separadas en preventivo y correctivo.
     *
     * @return array{meses: list<string>, preventivo: list<int>, correctivo: list<int>, anio: int}
     */
    public function mantenimientosPorMes(?int $anio = null): array
    {
        $anio ??= (int) date('Y');
        $preventivo = array_fill(0, 12, 0);
        $correctivo = array_fill(0, 12, 0);
        foreach ($this->modelo->actasPorMes($anio) as $fila) {
            $i = (int) $fila['mes'] - 1;
            if ($fila['tipo'] === 'PREVENTIVO') {
                $preventivo[$i] = (int) $fila['total'];
            } else {
                $correctivo[$i] = (int) $fila['total'];
            }
        }

        return ['meses' => self::MESES, 'preventivo' => $preventivo, 'correctivo' => $correctivo, 'anio' => $anio];
    }

    /**
     * @return array{vencidos: list<array<string, mixed>>, total_vencidos: int, fin_vida: list<array<string, mixed>>}
     */
    public function alertas(): array
    {
        return [
            'vencidos'       => $this->modelo->mantenimientosVencidos(self::LIMITE_ALERTAS),
            'total_vencidos' => $this->modelo->contarVencidos(),
            'fin_vida'       => $this->modelo->finVidaUtil(self::LIMITE_ALERTAS),
        ];
    }
}
