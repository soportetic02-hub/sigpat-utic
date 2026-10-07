<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{q: string, tipo: ?string, estado: ?string, condicion: ?string, oficina_id: ?int, recomendado: bool} $filtros
 * @var array<string, scalar|null> $enlace
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 */
$iconos = ['PC' => 'fa-desktop', 'LAPTOP' => 'fa-laptop', 'IMPRESORA' => 'fa-print'];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-desktop me-2 text-primary"></i>Inventario de equipos</h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('equipos/exportar', $enlace)) ?>" class="btn btn-outline-success">
            <i class="fa-solid fa-file-csv me-1"></i>Exportar CSV
        </a>
        <a href="<?= e(url('equipos/crear')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Registrar equipo</a>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('equipos')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="100"
                       placeholder="Serie, patrimonial, hostname, IP, MAC…" value="<?= e($filtros['q']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="tipo" class="form-label small mb-1">Tipo</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['PC', 'LAPTOP', 'IMPRESORA'] as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filtros['tipo'] === $t ? 'selected' : '' ?>><?= e(etiqueta($t)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="estado" class="form-label small mb-1">Estado operativo</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Vigentes (sin bajas)</option>
                    <?php foreach (['OPERATIVO', 'EN_MANTENIMIENTO', 'INOPERATIVO', 'DE_BAJA'] as $s): ?>
                        <option value="<?= e($s) ?>" <?= $filtros['estado'] === $s ? 'selected' : '' ?>><?= e(etiqueta($s)) ?></option>
                    <?php endforeach; ?>
                    <option value="TODOS" <?= $filtros['estado'] === 'TODOS' ? 'selected' : '' ?>>Todos (incluye bajas)</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label for="condicion" class="form-label small mb-1">Condición</label>
                <select id="condicion" name="condicion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach (['BUENO', 'REGULAR', 'MALO'] as $c): ?>
                        <option value="<?= e($c) ?>" <?= $filtros['condicion'] === $c ? 'selected' : '' ?>><?= e(etiqueta($c)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
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
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <a href="<?= e(url('equipos')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
            <div class="col-12">
                <div class="form-check form-check-inline small">
                    <input class="form-check-input" type="checkbox" id="recomendado" name="recomendado" value="1" <?= $filtros['recomendado'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="recomendado">Solo equipos con recomendación de baja</label>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Equipo</th>
                        <th>Serie / Patrimonial</th>
                        <th class="d-none d-lg-table-cell">Red</th>
                        <th>Ubicación / Responsable</th>
                        <th>Estado</th>
                        <th class="d-none d-xl-table-cell">Próx. mantenimiento</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">No se encontraron equipos con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $eq):
                    $retraso = $eq['dias_retraso_mantenimiento'] === null ? null : (int) $eq['dias_retraso_mantenimiento'];
                    ?>
                    <tr>
                        <td>
                            <i class="fa-solid <?= e($iconos[$eq['tipo']] ?? 'fa-desktop') ?> text-primary fa-fw me-1"></i>
                            <a href="<?= e(url('equipos/' . $eq['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($eq['marca'] . ' ' . $eq['modelo']) ?></a>
                            <div class="small text-body-secondary"><?= e(etiqueta($eq['tipo'])) ?> #<?= e($eq['id']) ?>
                                <?php if ($eq['licencia_id'] !== null): ?><span class="ms-1" title="Tiene Office instalado con una cuenta Microsoft 365"><i class="fa-brands fa-microsoft"></i></span><?php endif; ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= e($eq['nro_serie'] ?? '—') ?>
                            <div class="text-body-secondary"><?= e($eq['codigo_patrimonial'] ?? '—') ?></div>
                        </td>
                        <td class="small d-none d-lg-table-cell">
                            <?= e($eq['hostname'] ?? '—') ?>
                            <div class="text-body-secondary"><?= e($eq['ip_lan'] ?? '') ?></div>
                        </td>
                        <td class="small">
                            <?= e($eq['oficina_siglas'] ?? $eq['oficina_nombre']) ?>
                            <div class="text-body-secondary"><?= e($eq['personal_nombre'] ?? 'Sin responsable') ?></div>
                        </td>
                        <td>
                            <span class="badge <?= e(clase_estado($eq['estado_operativo'])) ?>"><?= e(etiqueta($eq['estado_operativo'])) ?></span>
                            <span class="badge <?= e(clase_condicion($eq['condicion_fisica'])) ?>"><?= e(etiqueta($eq['condicion_fisica'])) ?></span>
                            <?php if ((int) $eq['recomendado_baja'] === 1 && $eq['estado_operativo'] !== 'DE_BAJA'): ?>
                                <span class="badge text-bg-danger-subtle text-danger-emphasis border border-danger-subtle" title="Recomendado para baja"><i class="fa-solid fa-triangle-exclamation"></i> Baja</span>
                            <?php endif; ?>
                        </td>
                        <td class="small d-none d-xl-table-cell">
                            <?php if ($eq['fecha_proximo_mantenimiento'] === null): ?>
                                <span class="text-body-secondary">—</span>
                            <?php else: ?>
                                <span class="<?= $retraso !== null && $retraso > 0 ? 'text-danger fw-semibold' : '' ?>"><?= e(fecha($eq['fecha_proximo_mantenimiento'])) ?></span>
                                <?php if ($retraso !== null && $retraso > 0 && $eq['estado_operativo'] !== 'DE_BAJA'): ?>
                                    <div class="text-danger">Vencido hace <?= e($retraso) ?> día(s)</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('equipos/' . $eq['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Ver ficha"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($eq['estado_operativo'] !== 'DE_BAJA'): ?>
                                <a href="<?= e(url('equipos/' . $eq['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'equipos', 'filtros' => $enlace]) ?>
    </div>
</div>
