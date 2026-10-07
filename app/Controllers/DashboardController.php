<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Services\DashboardService;

/**
 * Página de inicio: KPIs, gráficos y alertas.
 */
final class DashboardController extends Controller
{
    public function index(): void
    {
        $servicio = new DashboardService();

        $this->view('dashboard/index', [
            'titulo'  => 'Dashboard',
            'usuario' => Auth::user(),
            'kpis'    => $servicio->kpis(),
            'meses'   => $servicio->mantenimientosPorMes(),
            'alertas' => $servicio->alertas(),
        ]);
    }
}
