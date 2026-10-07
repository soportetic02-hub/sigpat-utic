<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{cuentas: int, ocupados: int, capacidad: int, disponibles: int, llenas: int, por_verificar: int} $totales
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 * @var list<string> $estados
 * @var array{q: string, estado: string, oficina_id: ?int, cupo: ?string} $filtros
 */
$porcentajeGlobal = $totales['capacidad'] > 0 ? (int) round($totales['ocupados'] * 100 / $totales['capacidad']) : 0;
$claseEstado = ['ACTIVA' => 'text-bg-success', 'SUSPENDIDA' => 'text-bg-warning', 'VENCIDA' => 'text-bg-danger', 'BAJA' => 'text-bg-dark'];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-brands fa-microsoft me-2 text-primary"></i>Licencias Microsoft 365</h1>
    <?php if (has_role('ADMINISTRADOR')): ?>
        <a href="<?= e(url('licencias/crear')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Registrar cuenta</a>
    <?php endif; ?>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body d-flex flex-wrap align-items-center gap-4">
        <div>
            <div class="small text-body-secondary">Cuentas</div>
            <div class="fs-4 fw-bold"><?= e($totales['cuentas']) ?></div>
        </div>
        <div>
            <div class="small text-body-secondary">Instalaciones usadas</div>
            <div class="fs-4 fw-bold"><?= e($totales['ocupados']) ?>/<?= e($totales['capacidad']) ?></div>
        </div>
        <div>
            <div class="small text-body-secondary">Disponibles</div>
            <div class="fs-4 fw-bold"><?= e($totales['disponibles']) ?></div>
        </div>
        <div>
            <div class="small text-body-secondary">Cuentas llenas</div>
            <div class="fs-4 fw-bold"><?= e($totales['llenas']) ?></div>
        </div>
        <?php if ($totales['por_verificar'] > 0): ?>
            <div>
                <div class="small text-body-secondary">Por verificar</div>
                <div class="fs-4 fw-bold text-warning-emphasis"><?= e($totales['por_verificar']) ?></div>
            </div>
        <?php endif; ?>
        <div class="flex-grow-1" style="min-width: 200px;">
            <div class="small text-body-secondary mb-1">Uso global (<?= e($porcentajeGlobal) ?> %)</div>
            <div class="progress" role="progressbar" aria-label="Uso global" aria-valuenow="<?= e($porcentajeGlobal) ?>" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: <?= e($porcentajeGlobal) ?>%"></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('licencias')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-4">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="60"
                       placeholder="Correo, código, persona o equipo" value="<?= e($filtros['q']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($estados as $es): ?>
                        <option value="<?= e($es) ?>" <?= $filtros['estado'] === $es ? 'selected' : '' ?>><?= e(etiqueta($es)) ?></option>
                    <?php endforeach; ?>
                    <option value="DESACTIVADAS" <?= $filtros['estado'] === 'DESACTIVADAS' ? 'selected' : '' ?>>Desactivadas</option>
                </select>
            </div>
            <div class="col-6 col-md-4 col-lg-3">
                <label for="oficina_id" class="form-label small mb-1">Oficina</label>
                <select id="oficina_id" name="oficina_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($oficinas as $op): ?>
                        <option value="<?= e($op['id']) ?>" <?= $filtros['oficina_id'] === $op['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2 col-lg-1 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="cupo" name="cupo" value="1" <?= $filtros['cupo'] !== null ? 'checked' : '' ?>>
                    <label class="form-check-label small text-nowrap" for="cupo">Con cupo</label>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <a href="<?= e(url('licencias')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros" aria-label="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Código</th>
                        <th>Correo</th>
                        <th class="d-none d-lg-table-cell">Plan</th>
                        <th>Estado</th>
                        <th style="min-width: 170px;">Uso</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="6" class="text-center text-body-secondary py-4">No se encontraron cuentas.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $l):
                    $ocupados = (int) $l['ocupados'];
                    $capacidad = max(1, min((int) $l['max_instalaciones'], 5));
                    $porcentaje = (int) round($ocupados * 100 / $capacidad);
                    $color = $ocupados >= $capacidad ? 'bg-danger' : ($ocupados >= $capacidad - 1 ? 'bg-warning' : 'bg-primary');
                    ?>
                    <tr class="<?= (int) $l['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                        <td class="text-nowrap"><a href="<?= e(url('licencias/' . $l['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($l['codigo']) ?></a></td>
                        <td class="small text-break"><?= e($l['correo']) ?></td>
                        <td class="d-none d-lg-table-cell small"><?= e($l['plan']) ?></td>
                        <td>
                            <?php if ((int) $l['activo'] !== 1): ?>
                                <span class="badge text-bg-light border">Desactivada</span>
                            <?php else: ?>
                                <span class="badge <?= e($claseEstado[$l['estado']] ?? 'text-bg-light border') ?>"><?= e(etiqueta($l['estado'])) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $l['por_verificar'] > 0): ?>
                                <span class="badge text-bg-warning" title="Instalaciones por verificar"><i class="fa-solid fa-circle-question me-1"></i><?= e($l['por_verificar']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge text-bg-light border font-monospace"><?= e($ocupados) ?>/<?= e($capacidad) ?></span>
                                <div class="progress flex-grow-1" style="height: 8px;" role="progressbar" aria-label="Uso de la cuenta" aria-valuenow="<?= e($porcentaje) ?>" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar <?= e($color) ?>" style="width: <?= e($porcentaje) ?>%"></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-end"><a href="<?= e(url('licencias/' . $l['id'])) ?>" class="btn btn-sm btn-outline-primary" title="Ver instalaciones" aria-label="Ver instalaciones"><i class="fa-solid fa-table-cells-large"></i></a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'licencias', 'filtros' => $filtros]) ?>
    </div>
</div>
