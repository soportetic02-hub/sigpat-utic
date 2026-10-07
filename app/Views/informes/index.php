<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{desde: ?string, hasta: ?string, accion: ?string, estado: ?string, q: string} $filtros
 * @var list<string> $acciones
 */
$claseAccion = ['REPARACION' => 'text-bg-info', 'INOPERATIVIDAD' => 'text-bg-warning', 'BAJA_DEFINITIVA' => 'text-bg-danger', 'REEMPLAZO' => 'text-bg-primary'];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Informes técnicos</h1>
    <a href="<?= e(url('informes/crear')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nuevo informe</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('informes')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="60" placeholder="N°, asunto, serie o patrimonial" value="<?= e($filtros['q']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="desde" class="form-label small mb-1">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="hasta" class="form-label small mb-1">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="accion" class="form-label small mb-1">Acción requerida</label>
                <select id="accion" name="accion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e(etiqueta($a)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="BORRADOR" <?= $filtros['estado'] === 'BORRADOR' ? 'selected' : '' ?>>Borrador</option>
                    <option value="EMITIDO" <?= $filtros['estado'] === 'EMITIDO' ? 'selected' : '' ?>>Emitido</option>
                </select>
            </div>
            <div class="col-12 col-lg-2 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <a href="<?= e(url('informes')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr><th>N°</th><th>Asunto</th><th>Fecha</th><th>Acción</th><th class="text-center d-none d-md-table-cell">Equipos</th><th class="text-center d-none d-md-table-cell">Fotos</th><th class="d-none d-lg-table-cell">Elaboró</th><th>Estado</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="9" class="text-center text-body-secondary py-4">No hay informes con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $i): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= $i['numero'] !== null ? 'N° ' . e($i['numero']) : '<span class="text-body-secondary">Borrador #' . e($i['id']) . '</span>' ?></td>
                        <td class="small"><a href="<?= e(url('informes/' . $i['id'])) ?>" class="text-decoration-none"><?= e($i['asunto']) ?></a></td>
                        <td class="small text-nowrap"><?= e(fecha((string) $i['fecha'])) ?></td>
                        <td><span class="badge <?= e($claseAccion[$i['accion_requerida']] ?? 'text-bg-light') ?>"><?= e(etiqueta($i['accion_requerida'])) ?></span></td>
                        <td class="text-center d-none d-md-table-cell"><?= e($i['total_equipos']) ?></td>
                        <td class="text-center d-none d-md-table-cell"><?= e($i['total_evidencias']) ?></td>
                        <td class="small d-none d-lg-table-cell"><?= e($i['de_nombre']) ?></td>
                        <td><span class="badge <?= $i['estado'] === 'EMITIDO' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($i['estado'])) ?></span></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('informes/' . $i['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($i['estado'] === 'EMITIDO'): ?>
                                <a href="<?= e(url('informes/' . $i['id'] . '/pdf')) ?>" class="btn btn-sm btn-outline-danger" title="Vista previa PDF" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i></a>
                                <a href="<?= e(url('informes/' . $i['id'] . '/word')) ?>" class="btn btn-sm btn-outline-primary" title="Descargar Word"><i class="fa-solid fa-file-word"></i></a>
                            <?php else: ?>
                                <a href="<?= e(url('informes/' . $i['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'informes', 'filtros' => $filtros]) ?>
    </div>
</div>
