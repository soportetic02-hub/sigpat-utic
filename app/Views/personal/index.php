<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{q: string, oficina_id: int|string, estado: string} $filtros
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 */
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i>Personal</h1>
    <a href="<?= e(url('personal/crear', ['oficina_id' => $filtros['oficina_id']])) ?>" class="btn btn-primary">
        <i class="fa-solid fa-user-plus me-1"></i>Registrar personal
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('personal')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-4">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="100"
                       placeholder="Nombres, apellidos, DNI, correo o cargo" value="<?= e($filtros['q']) ?>">
            </div>
            <div class="col-12 col-md-3">
                <label for="oficina_id" class="form-label small mb-1">Oficina</label>
                <select id="oficina_id" name="oficina_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($oficinas as $op): ?>
                        <option value="<?= e($op['id']) ?>" <?= (string) $filtros['oficina_id'] === (string) $op['id'] ? 'selected' : '' ?>>
                            <?= str_repeat('&nbsp;&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="activos" <?= $filtros['estado'] === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $filtros['estado'] === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                    <option value="todos" <?= $filtros['estado'] === 'todos' ? 'selected' : '' ?>>Todos</option>
                </select>
            </div>
            <div class="col-6 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <a href="<?= e(url('personal')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Apellidos y nombres</th>
                        <th class="d-none d-md-table-cell">DNI</th>
                        <th class="d-none d-lg-table-cell">Cargo</th>
                        <th>Oficina</th>
                        <th class="text-center">Equipos</th>
                        <th>Estado</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">No se encontró personal con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $p): ?>
                    <tr class="<?= (int) $p['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                        <td>
                            <a href="<?= e(url('personal/' . $p['id'])) ?>" class="fw-semibold text-decoration-none"><?= e($p['apellidos'] . ', ' . $p['nombres']) ?></a>
                            <?php if ($p['email'] !== null): ?><div class="small text-body-secondary"><?= e($p['email']) ?></div><?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?= e($p['dni'] ?? '—') ?></td>
                        <td class="d-none d-lg-table-cell small"><?= e($p['cargo'] ?? '—') ?></td>
                        <td class="small"><?= e($p['oficina_siglas'] ?? $p['oficina_nombre']) ?></td>
                        <td class="text-center">
                            <?php if ((int) $p['total_equipos'] > 0): ?>
                                <span class="badge rounded-pill text-bg-primary"><?= e($p['total_equipos']) ?></span>
                            <?php else: ?>
                                <span class="text-body-secondary">0</span>
                            <?php endif; ?>
                        </td>
                        <td><?= (int) $p['activo'] === 1 ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-light border">Inactivo</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('personal/' . $p['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Ver detalle"><i class="fa-solid fa-eye"></i></a>
                            <a href="<?= e(url('personal/' . $p['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'personal', 'filtros' => $filtros]) ?>
    </div>
</div>
