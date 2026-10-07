<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{desde: ?string, hasta: ?string, tecnico_id: ?int, oficina_id: ?int, tipo: ?string, estado: ?string, firma: ?string, envio: ?string, q: string} $filtros
 * @var list<array{id: int, nombre: string}> $tecnicos
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 * @var bool $requiereFirma
 * @var bool $esAdmin
 */
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-screwdriver-wrench me-2 text-primary"></i>Actas de mantenimiento</h1>
    <a href="<?= e(url('mantenimientos/crear')) ?>" class="btn btn-primary"><i class="fa-solid fa-plus me-1"></i>Nueva acta</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('mantenimientos')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-lg-3">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="60" placeholder="N° de acta, serie, hostname o usuario" value="<?= e($filtros['q']) ?>">
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
                <label for="tecnico_id" class="form-label small mb-1">Técnico</label>
                <select id="tecnico_id" name="tecnico_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($tecnicos as $t): ?>
                        <option value="<?= e($t['id']) ?>" <?= $filtros['tecnico_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-3">
                <label for="oficina_id" class="form-label small mb-1">Oficina</label>
                <select id="oficina_id" name="oficina_id" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($oficinas as $op): ?>
                        <option value="<?= e($op['id']) ?>" <?= $filtros['oficina_id'] === $op['id'] ? 'selected' : '' ?>><?= str_repeat('&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="tipo" class="form-label small mb-1">Tipo</label>
                <select id="tipo" name="tipo" class="form-select">
                    <option value="">Todos</option>
                    <option value="PREVENTIVO" <?= $filtros['tipo'] === 'PREVENTIVO' ? 'selected' : '' ?>>Preventivo</option>
                    <option value="CORRECTIVO" <?= $filtros['tipo'] === 'CORRECTIVO' ? 'selected' : '' ?>>Correctivo</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="BORRADOR" <?= $filtros['estado'] === 'BORRADOR' ? 'selected' : '' ?>>Borrador</option>
                    <option value="CERRADA" <?= $filtros['estado'] === 'CERRADA' ? 'selected' : '' ?>>Cerrada</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="firma" class="form-label small mb-1">Firma</label>
                <select id="firma" name="firma" class="form-select">
                    <option value="">Todas</option>
                    <option value="PENDIENTE" <?= $filtros['firma'] === 'PENDIENTE' ? 'selected' : '' ?>>Pendiente de firma</option>
                    <option value="FIRMADA" <?= $filtros['firma'] === 'FIRMADA' ? 'selected' : '' ?>>Firmada</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="envio" class="form-label small mb-1">Envío</label>
                <select id="envio" name="envio" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach (['NO_ENVIADO', 'ENVIADO', 'RECIBIDO', 'ERROR'] as $en): ?>
                        <option value="<?= e($en) ?>" <?= $filtros['envio'] === $en ? 'selected' : '' ?>><?= e(etiqueta($en)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-6 col-lg-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
                <a href="<?= e(url('mantenimientos')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>N° de acta</th>
                        <th>Equipo</th>
                        <th class="d-none d-md-table-cell">Usuario / Oficina</th>
                        <th>Tipo</th>
                        <th class="d-none d-lg-table-cell">Ingreso / Salida</th>
                        <th class="d-none d-lg-table-cell">Técnico</th>
                        <th>Estado</th>
                        <th class="text-end"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No hay actas con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $a): ?>
                    <tr>
                        <td class="fw-semibold text-nowrap"><?= $a['numero'] !== null ? 'N° ' . e($a['numero']) : '<span class="text-body-secondary">Borrador #' . e($a['id']) . '</span>' ?></td>
                        <td class="small">
                            <?= e($a['equipo_tipo'] . ' ' . $a['marca'] . ' ' . $a['modelo']) ?>
                            <div class="text-body-secondary"><?= e($a['nro_serie'] ?? $a['codigo_patrimonial'] ?? '') ?></div>
                        </td>
                        <td class="small d-none d-md-table-cell">
                            <?= e($a['personal_nombre'] ?? 'Sin responsable') ?>
                            <div class="text-body-secondary"><?= e($a['oficina_siglas'] ?? $a['oficina_nombre']) ?></div>
                        </td>
                        <td><span class="badge <?= $a['tipo'] === 'PREVENTIVO' ? 'text-bg-info' : 'text-bg-warning' ?>"><?= e(etiqueta($a['tipo'])) ?></span></td>
                        <td class="small d-none d-lg-table-cell text-nowrap">
                            <?= e(fecha((string) $a['fecha_ingreso'], true)) ?>
                            <div class="text-body-secondary"><?= e($a['fecha_salida'] !== null ? fecha((string) $a['fecha_salida'], true) : '—') ?></div>
                        </td>
                        <td class="small d-none d-lg-table-cell"><?= e($a['tecnico_nombre']) ?></td>
                        <td>
                            <span class="badge <?= $a['estado'] === 'CERRADA' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($a['estado'])) ?></span>
                            <?php if ($a['estado'] === 'CERRADA'): ?>
                                <div class="d-flex flex-wrap gap-1 mt-1">
                                    <?php if ($requiereFirma || $a['firmado_at'] !== null): ?>
                                        <span class="badge <?= $a['firmado_at'] !== null ? 'text-bg-success' : 'text-bg-light border' ?>" title="<?= $a['firmado_at'] !== null ? 'Firmada digitalmente' : 'Pendiente de firma' ?>"><i class="fa-solid fa-signature"></i></span>
                                    <?php endif; ?>
                                    <span class="badge <?= e(clase_envio($a['estado_envio'])) ?>"><?= e(etiqueta($a['estado_envio'])) ?></span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('mantenimientos/' . $a['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Ver"><i class="fa-solid fa-eye"></i></a>
                            <?php if ($a['estado'] === 'BORRADOR'): ?>
                                <a href="<?= e(url('mantenimientos/' . $a['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Continuar"><i class="fa-solid fa-pen"></i></a>
                            <?php else: ?>
                                <a href="<?= e(url('mantenimientos/' . $a['id'] . '/pdf')) ?>" class="btn btn-sm btn-outline-danger" title="Vista previa PDF" target="_blank" rel="noopener"><i class="fa-solid fa-file-pdf"></i></a>
                            <?php endif; ?>
                            <?php if ($esAdmin && $a['estado_envio'] !== 'RECIBIDO'): ?>
                                <form method="post" action="<?= e(url('mantenimientos/' . $a['id'] . '/eliminar')) ?>" class="d-inline"
                                      data-confirm="<?= e($a['estado'] === 'BORRADOR'
                                          ? '¿Eliminar este borrador? El equipo volverá a su estado anterior.'
                                          : '¿Eliminar el acta N° ' . $a['numero'] . '? Se borrarán el acta, sus envíos y su PDF. Esta acción no se puede deshacer.') ?>">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar acta" aria-label="Eliminar acta"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'mantenimientos', 'filtros' => $filtros]) ?>
    </div>
</div>
