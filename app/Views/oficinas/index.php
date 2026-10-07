<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var string $vista  'arbol' | 'lista'
 * @var list<array<string, mixed>> $arbol
 * @var bool $inactivasEnArbol
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}|null $pagina
 * @var array{vista: string, q: string, tipo: string, estado: string} $filtros
 * @var list<string> $tipos
 */
$esAdmin = has_role('ADMINISTRADOR');
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-sitemap me-2 text-primary"></i>Oficinas</h1>
    <a href="<?= e(url('oficinas/crear')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nueva oficina</a>
</div>

<ul class="nav nav-tabs mb-0">
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'arbol' ? 'active' : '' ?>" href="<?= e(url('oficinas')) ?>">
            <i class="fa-solid fa-diagram-project me-1"></i>Árbol
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $vista === 'lista' ? 'active' : '' ?>" href="<?= e(url('oficinas', ['vista' => 'lista'])) ?>">
            <i class="fa-solid fa-list me-1"></i>Lista
        </a>
    </li>
</ul>

<div class="card shadow-sm border-0 border-top-0 rounded-top-0">
    <div class="card-body">
        <?php if ($vista === 'arbol'): ?>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <p class="small text-body-secondary mb-0">
                    Estructura jerárquica. Los números indican personal activo y equipos no dados de baja.
                </p>
                <form method="get" action="<?= e(url('oficinas')) ?>">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" role="switch" id="inactivas" name="inactivas" value="1"
                               <?= $inactivasEnArbol ? 'checked' : '' ?> onchange="this.form.submit()">
                        <label class="form-check-label small" for="inactivas">Mostrar inactivas</label>
                    </div>
                </form>
            </div>

            <?php if ($arbol === []): ?>
                <p class="text-center text-body-secondary py-4 mb-0">No hay oficinas registradas.</p>
            <?php else: ?>
                <?= View::partial('oficinas/_arbol', ['nodos' => $arbol, 'esAdmin' => $esAdmin]) ?>
            <?php endif; ?>
        <?php else: ?>
            <form method="get" action="<?= e(url('oficinas')) ?>" class="row g-2 align-items-end mb-3">
                <input type="hidden" name="vista" value="lista">
                <div class="col-12 col-md-5">
                    <label for="q" class="form-label small mb-1">Buscar</label>
                    <input type="search" id="q" name="q" class="form-control" maxlength="100" placeholder="Nombre, siglas o ubicación" value="<?= e($filtros['q']) ?>">
                </div>
                <div class="col-6 col-md-2">
                    <label for="tipo" class="form-label small mb-1">Tipo</label>
                    <select id="tipo" name="tipo" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= e($t) ?>" <?= $filtros['tipo'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-md-2">
                    <label for="estado" class="form-label small mb-1">Estado</label>
                    <select id="estado" name="estado" class="form-select">
                        <option value="">Todas</option>
                        <option value="activas" <?= $filtros['estado'] === 'activas' ? 'selected' : '' ?>>Activas</option>
                        <option value="inactivas" <?= $filtros['estado'] === 'inactivas' ? 'selected' : '' ?>>Inactivas</option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                    <a href="<?= e(url('oficinas', ['vista' => 'lista'])) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Oficina</th>
                            <th>Tipo</th>
                            <th class="d-none d-md-table-cell">Depende de</th>
                            <th class="text-center">Personal</th>
                            <th class="text-center">Equipos</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ($pagina['data'] === []): ?>
                        <tr><td colspan="7" class="text-center text-body-secondary py-4">No se encontraron oficinas.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($pagina['data'] as $o): ?>
                        <tr class="<?= (int) $o['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                            <td>
                                <span class="fw-semibold"><?= e($o['nombre']) ?></span>
                                <?php if ($o['siglas'] !== null): ?><span class="text-body-secondary">(<?= e($o['siglas']) ?>)</span><?php endif; ?>
                                <?php if ($o['ubicacion'] !== null): ?><div class="small text-body-secondary"><i class="fa-solid fa-location-dot me-1"></i><?= e($o['ubicacion']) ?></div><?php endif; ?>
                            </td>
                            <td><span class="badge text-bg-light border"><?= e($o['tipo']) ?></span></td>
                            <td class="d-none d-md-table-cell small"><?= e($o['padre_nombre'] ?? '—') ?></td>
                            <td class="text-center">
                                <a href="<?= e(url('personal', ['oficina_id' => $o['id']])) ?>" class="text-decoration-none"><?= e($o['personal_activos']) ?></a>
                            </td>
                            <td class="text-center"><?= e($o['equipos_activos']) ?></td>
                            <td>
                                <?= (int) $o['activo'] === 1 ? '<span class="badge text-bg-success">Activa</span>' : '<span class="badge text-bg-light border">Inactiva</span>' ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <a href="<?= e(url('oficinas/' . $o['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                                <?php if ($esAdmin): ?>
                                    <form method="post" action="<?= e(url('oficinas/' . $o['id'] . '/activo')) ?>" class="d-inline"
                                          data-confirm="<?= e((int) $o['activo'] === 1 ? '¿Desactivar la oficina ' . $o['nombre'] . '?' : '¿Activar la oficina ' . $o['nombre'] . '?') ?>">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="volver" value="lista">
                                        <?php if ((int) $o['activo'] === 1): ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar"><i class="fa-solid fa-ban"></i></button>
                                        <?php else: ?>
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Activar"><i class="fa-solid fa-rotate-left"></i></button>
                                        <?php endif; ?>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'oficinas', 'filtros' => $filtros]) ?>
        <?php endif; ?>
    </div>
</div>
