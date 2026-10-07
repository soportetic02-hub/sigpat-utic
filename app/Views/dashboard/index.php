<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $usuario
 * @var array<string, array<string, int>> $kpis
 * @var array{meses: list<string>, preventivo: list<int>, correctivo: list<int>, anio: int} $meses
 * @var array{vencidos: list<array<string, mixed>>, total_vencidos: int, fin_vida: list<array<string, mixed>>} $alertas
 */
$eq = $kpis['equipos'];
$mt = $kpis['mantenimientos'];
$li = $kpis['licencias'];
$rg = $kpis['riesgo'];
$hora = (int) date('G');
$saludo = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$tipos = [
    ['clave' => 'pc', 'nombre' => 'PC', 'serie' => 1],
    ['clave' => 'laptop', 'nombre' => 'Laptop', 'serie' => 2],
    ['clave' => 'impresora', 'nombre' => 'Impresora', 'serie' => 3],
];
$totalAnio = array_sum($meses['preventivo']) + array_sum($meses['correctivo']);
$datosGraficos = [
    'tipos' => array_map(static fn (array $t): array => ['nombre' => $t['nombre'], 'valor' => $eq[$t['clave']], 'serie' => $t['serie']], $tipos),
    'meses' => $meses,
];
?>
<div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0"><?= e($saludo) ?>, <?= e($usuario['nombres']) ?></h1>
        <span class="small text-body-secondary">Resumen del parque informático al <?= e(fecha(date('Y-m-d'))) ?> · no incluye equipos dados de baja</span>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= e(url('mantenimientos/crear')) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Nueva acta</a>
        <a href="<?= e(url('informes/crear')) ?>" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-file-lines me-1"></i>Nuevo informe</a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-3">
    <div class="col-sm-6 col-xl-3">
        <a href="<?= e(url('equipos')) ?>" class="card kpi-card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="kpi-titulo">Equipos</span>
                    <span class="kpi-icono bg-primary-subtle text-primary"><i class="fa-solid fa-desktop"></i></span>
                </div>
                <div class="kpi-valor"><?= e($eq['total']) ?></div>
                <div class="small text-body-secondary">PC <?= e($eq['pc']) ?> · Laptop <?= e($eq['laptop']) ?> · Impresora <?= e($eq['impresora']) ?></div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= e(url('mantenimientos', ['estado' => 'CERRADA', 'desde' => $mt['anio'] . '-01-01'])) ?>" class="card kpi-card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="kpi-titulo">Mantenimientos <?= e($mt['anio']) ?></span>
                    <span class="kpi-icono bg-success-subtle text-success"><i class="fa-solid fa-screwdriver-wrench"></i></span>
                </div>
                <div class="kpi-valor"><?= e($mt['cerradas']) ?> <span class="kpi-unidad">actas cerradas</span></div>
                <div class="small <?= $mt['pendientes'] > 0 ? 'text-warning-emphasis' : 'text-body-secondary' ?>">
                    <?php if ($mt['pendientes'] > 0): ?><i class="fa-solid fa-triangle-exclamation me-1"></i><?php endif; ?>
                    <?= e($mt['pendientes']) ?> equipo(s) pendiente(s)<?= $mt['nunca'] > 0 ? ' · ' . e($mt['nunca']) . ' nunca mantenido(s)' : '' ?>
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= e(url('licencias')) ?>" class="card kpi-card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="kpi-titulo">Licencias Microsoft 365</span>
                    <span class="kpi-icono bg-info-subtle text-info-emphasis"><i class="fa-brands fa-microsoft"></i></span>
                </div>
                <div class="kpi-valor"><?= e($li['ocupados']) ?>/<?= e($li['capacidad']) ?> <span class="kpi-unidad">instalaciones</span></div>
                <div class="progress my-1" style="height: 6px;" role="progressbar" aria-label="Instalaciones usadas" aria-valuenow="<?= e($li['porcentaje']) ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar" style="width: <?= e($li['porcentaje']) ?>%"></div>
                </div>
                <div class="small text-body-secondary">
                    <?= e($li['cuentas']) ?> cuenta(s) · <?= e($li['disponibles']) ?> disponible(s) · <?= e($li['llenas']) ?> llena(s)
                </div>
            </div>
        </a>
    </div>
    <div class="col-sm-6 col-xl-3">
        <a href="<?= e(url('equipos', ['recomendado' => 1])) ?>" class="card kpi-card border-0 shadow-sm h-100 text-decoration-none text-body">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <span class="kpi-titulo">Equipos en riesgo</span>
                    <span class="kpi-icono bg-danger-subtle text-danger"><i class="fa-solid fa-triangle-exclamation"></i></span>
                </div>
                <div class="kpi-valor"><?= e($rg['total']) ?></div>
                <div class="small text-body-secondary">Condición mala <?= e($rg['malos']) ?> · Recomendados para baja <?= e($rg['recomendados']) ?> · Vida útil cumplida <?= e($rg['vida_cumplida']) ?></div>
            </div>
        </a>
    </div>
</div>

<!-- Gráficos -->
<div class="row g-3 mb-3">
    <div class="col-lg-4">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 mb-1">Equipos por tipo</h2>
                <p class="small text-body-secondary mb-3">Total vigente: <?= e($eq['total']) ?></p>
                <?php if ($eq['total'] === 0): ?>
                    <p class="text-body-secondary small mb-0">Aún no hay equipos registrados.</p>
                <?php else: ?>
                    <div class="grafico-dona"><canvas id="grafico-tipos" role="img" aria-label="Gráfico de dona: equipos por tipo"></canvas></div>
                    <ul class="leyenda list-unstyled mt-3 mb-0">
                        <?php foreach ($tipos as $t): ?>
                            <li class="d-flex align-items-center justify-content-between">
                                <span><span class="leyenda-marca serie-<?= e($t['serie']) ?>"></span><?= e($t['nombre']) ?></span>
                                <span class="fw-semibold"><?= e($eq[$t['clave']]) ?> <span class="text-body-secondary fw-normal">(<?= e($eq['total'] > 0 ? round($eq[$t['clave']] * 100 / $eq['total']) : 0) ?> %)</span></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                    <div>
                        <h2 class="h6 mb-1">Mantenimientos por mes (<?= e($meses['anio']) ?>)</h2>
                        <p class="small text-body-secondary mb-2">Actas cerradas: <?= e($totalAnio) ?></p>
                    </div>
                    <ul class="leyenda list-unstyled d-flex gap-3 mb-0 small">
                        <li><span class="leyenda-marca serie-1"></span>Preventivo</li>
                        <li><span class="leyenda-marca serie-2"></span>Correctivo</li>
                    </ul>
                </div>
                <div class="grafico-barras"><canvas id="grafico-meses" role="img" aria-label="Gráfico de barras: actas de mantenimiento cerradas por mes"></canvas></div>
                <details class="mt-2 small">
                    <summary class="text-body-secondary">Ver datos en tabla</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm mb-0 text-center">
                            <thead><tr><th class="text-start">Tipo</th><?php foreach ($meses['meses'] as $m): ?><th><?= e($m) ?></th><?php endforeach; ?></tr></thead>
                            <tbody>
                                <tr><td class="text-start">Preventivo</td><?php foreach ($meses['preventivo'] as $n): ?><td><?= e($n) ?></td><?php endforeach; ?></tr>
                                <tr><td class="text-start">Correctivo</td><?php foreach ($meses['correctivo'] as $n): ?><td><?= e($n) ?></td><?php endforeach; ?></tr>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>
</div>

<!-- Alertas -->
<div class="row g-3">
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-calendar-xmark me-1 text-danger"></i>Mantenimientos vencidos</h2>
                    <span class="badge text-bg-light border"><?= e($alertas['total_vencidos']) ?></span>
                </div>
                <?php if ($alertas['vencidos'] === []): ?>
                    <p class="small text-body-secondary mb-0"><i class="fa-solid fa-circle-check text-success me-1"></i>No hay mantenimientos vencidos.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Equipo</th><th>Oficina / Responsable</th><th>Último</th><th class="text-end">Retraso</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($alertas['vencidos'] as $v): ?>
                                <tr>
                                    <td class="small">
                                        <a href="<?= e(url('equipos/' . $v['id'])) ?>" class="text-decoration-none"><?= e(etiqueta($v['tipo']) . ' ' . $v['marca'] . ' ' . $v['modelo']) ?></a>
                                        <div class="text-body-secondary"><?= e($v['nro_serie'] ?? $v['codigo_patrimonial'] ?? '') ?></div>
                                    </td>
                                    <td class="small"><?= e($v['oficina_siglas'] ?? $v['oficina_nombre']) ?><div class="text-body-secondary"><?= e($v['personal_nombre'] ?? 'Sin responsable') ?></div></td>
                                    <td class="small text-nowrap"><?= e($v['fecha_ultimo_mantenimiento'] !== null ? fecha((string) $v['fecha_ultimo_mantenimiento']) : 'Nunca') ?></td>
                                    <td class="small text-end text-nowrap fw-semibold text-danger"><?= e($v['dias_retraso_mantenimiento']) ?> días</td>
                                    <td class="text-end">
                                        <?php if ($v['estado_operativo'] === 'EN_MANTENIMIENTO'): ?>
                                            <span class="badge text-bg-warning">En curso</span>
                                        <?php else: ?>
                                            <a href="<?= e(url('mantenimientos/crear', ['equipo_id' => $v['id']])) ?>" class="btn btn-sm btn-outline-primary text-nowrap" title="Registrar acta"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Registrar acta</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($alertas['total_vencidos'] > count($alertas['vencidos'])): ?>
                        <p class="small text-body-secondary mt-2 mb-0">Se muestran los <?= e(count($alertas['vencidos'])) ?> más atrasados de <?= e($alertas['total_vencidos']) ?>.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h6 mb-0"><i class="fa-solid fa-hourglass-end me-1 text-warning"></i>Equipos al final de su vida útil</h2>
                    <span class="badge text-bg-light border"><?= e($rg['vida_cumplida']) ?></span>
                </div>
                <?php if ($alertas['fin_vida'] === []): ?>
                    <p class="small text-body-secondary mb-0"><i class="fa-solid fa-circle-check text-success me-1"></i>Ningún equipo ha cumplido su vida útil.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Equipo</th><th>Antigüedad</th><th>Baja sugerida</th><th>Condición</th><th></th></tr></thead>
                            <tbody>
                            <?php foreach ($alertas['fin_vida'] as $f): ?>
                                <tr>
                                    <td class="small">
                                        <a href="<?= e(url('equipos/' . $f['id'])) ?>" class="text-decoration-none"><?= e(etiqueta($f['tipo']) . ' ' . $f['marca'] . ' ' . $f['modelo']) ?></a>
                                        <div class="text-body-secondary"><?= e($f['oficina_siglas'] ?? $f['oficina_nombre']) ?></div>
                                    </td>
                                    <td class="small text-nowrap"><?= e($f['anios_antiguedad']) ?> año(s)</td>
                                    <td class="small text-nowrap"><?= e(fecha((string) $f['fecha_sugerida_baja'])) ?></td>
                                    <td>
                                        <span class="badge <?= e(clase_condicion($f['condicion_fisica'])) ?>"><?= e(etiqueta($f['condicion_fisica'])) ?></span>
                                        <?php if ((int) $f['recomendado_baja'] === 1): ?><span class="badge text-bg-danger" title="Recomendado para baja"><i class="fa-solid fa-triangle-exclamation"></i></span><?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int) $f['recomendado_baja'] === 1): ?>
                                            <a href="<?= e(url('equipos/' . $f['id'])) ?>" class="btn btn-sm btn-outline-danger text-nowrap">Ver baja</a>
                                        <?php else: ?>
                                            <a href="<?= e(url('informes/crear', ['equipo_id' => $f['id']])) ?>" class="btn btn-sm btn-outline-primary text-nowrap" title="Crear informe"><i class="fa-solid fa-file-lines me-1"></i>Crear informe</a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="datos-dashboard"><?= json_encode($datosGraficos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        return;
    }
    var datos = JSON.parse(document.getElementById('datos-dashboard').textContent);
    // Colores desde los tokens de app.css (nada de HEX en el JS).
    var estilos = getComputedStyle(document.documentElement);
    var token = function (nombre) { return estilos.getPropertyValue(nombre).trim(); };
    var series = [token('--grafico-serie-1'), token('--grafico-serie-2'), token('--grafico-serie-3')];
    var superficie = token('--bs-body-bg');
    var tinta = token('--bs-body-color');
    var atenuado = token('--bs-secondary-color');
    var rejilla = token('--bs-border-color-translucent');
    var eje = token('--bs-border-color');
    Chart.defaults.font.family = 'system-ui, -apple-system, "Segoe UI", sans-serif';
    Chart.defaults.color = tinta;

    // Dona: equipos por tipo (colores en orden fijo; la leyenda HTML muestra los valores).
    var lienzoTipos = document.getElementById('grafico-tipos');
    if (lienzoTipos) {
        new Chart(lienzoTipos, {
            type: 'doughnut',
            data: {
                labels: datos.tipos.map(function (t) { return t.nombre; }),
                datasets: [{
                    data: datos.tipos.map(function (t) { return t.valor; }),
                    backgroundColor: datos.tipos.map(function (t) { return series[t.serie - 1]; }),
                    borderColor: superficie,
                    borderWidth: 2,
                    hoverOffset: 4
                }]
            },
            options: {
                maintainAspectRatio: false,
                cutout: '64%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (ctx) {
                                var total = ctx.dataset.data.reduce(function (a, b) { return a + b; }, 0);
                                var pct = total > 0 ? Math.round(ctx.parsed * 100 / total) : 0;
                                return ' ' + ctx.label + ': ' + ctx.parsed + ' (' + pct + ' %)';
                            }
                        }
                    }
                }
            }
        });
    }

    // Barras apiladas: actas cerradas por mes (preventivo abajo, correctivo arriba).
    var lienzoMeses = document.getElementById('grafico-meses');
    if (lienzoMeses) {
        new Chart(lienzoMeses, {
            type: 'bar',
            data: {
                labels: datos.meses.meses,
                datasets: [
                    { label: 'Preventivo', data: datos.meses.preventivo, backgroundColor: series[0], maxBarThickness: 22, borderSkipped: false, borderRadius: 0 },
                    {
                        label: 'Correctivo', data: datos.meses.correctivo, backgroundColor: series[1], maxBarThickness: 22,
                        borderColor: superficie, borderWidth: { top: 0, right: 0, left: 0, bottom: 2 }, borderSkipped: false,
                        borderRadius: { topLeft: 4, topRight: 4, bottomLeft: 0, bottomRight: 0 }
                    }
                ]
            },
            options: {
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { stacked: true, grid: { display: false }, border: { color: eje }, ticks: { color: atenuado } },
                    y: { stacked: true, beginAtZero: true, ticks: { precision: 0, color: atenuado }, grid: { color: rejilla }, border: { display: false } }
                },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            footer: function (items) {
                                var total = items.reduce(function (a, i) { return a + i.parsed.y; }, 0);
                                return 'Total: ' + total;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
