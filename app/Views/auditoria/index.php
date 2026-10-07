<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{usuario_id: ?int, accion: ?string, tabla: ?string, registro_id: string, desde: ?string, hasta: ?string} $filtros
 * @var list<string> $tablas
 * @var list<array{id: int, nombre: string}> $usuarios
 * @var list<string> $acciones
 */
$claseAccion = [
    'CREAR' => 'text-bg-success', 'EDITAR' => 'text-bg-primary', 'ELIMINAR' => 'text-bg-danger', 'BAJA' => 'text-bg-dark',
    'LOGIN' => 'text-bg-light border', 'LOGOUT' => 'text-bg-light border', 'LOGIN_FALLIDO' => 'text-bg-warning',
    'ASIGNAR' => 'text-bg-info', 'ASIGNAR_SLOT' => 'text-bg-info', 'LIBERAR_SLOT' => 'text-bg-secondary',
    'CERRAR' => 'text-bg-success', 'EMITIR' => 'text-bg-success', 'VER_CONTRASENA' => 'text-bg-warning',
    'FIRMAR' => 'text-bg-success', 'ENVIAR' => 'text-bg-info', 'CONFIRMAR_RECEPCION' => 'text-bg-success',
];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-clipboard-list me-2 text-primary"></i>Auditoría</h1>
    <span class="small text-body-secondary"><?= e($pagina['total']) ?> registro(s)</span>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('auditoria')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-6 col-md-3 col-lg-2">
                <label for="usuario_id" class="form-label small mb-1">Usuario</label>
                <select id="usuario_id" name="usuario_id" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($usuarios as $u): ?>
                        <option value="<?= e($u['id']) ?>" <?= $filtros['usuario_id'] === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['nombre']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="accion" class="form-label small mb-1">Acción</label>
                <select id="accion" name="accion" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($acciones as $a): ?>
                        <option value="<?= e($a) ?>" <?= $filtros['accion'] === $a ? 'selected' : '' ?>><?= e($a) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="tabla" class="form-label small mb-1">Tabla</label>
                <select id="tabla" name="tabla" class="form-select">
                    <option value="">Todas</option>
                    <?php foreach ($tablas as $t): ?>
                        <option value="<?= e($t) ?>" <?= $filtros['tabla'] === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-1">
                <label for="registro_id" class="form-label small mb-1">ID</label>
                <input type="text" id="registro_id" name="registro_id" class="form-control" maxlength="64" value="<?= e($filtros['registro_id']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="desde" class="form-label small mb-1">Desde</label>
                <input type="date" id="desde" name="desde" class="form-control" value="<?= e($filtros['desde']) ?>">
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label for="hasta" class="form-label small mb-1">Hasta</label>
                <input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($filtros['hasta']) ?>">
            </div>
            <div class="col-12 col-md-6 col-lg-1 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill" title="Filtrar"><i class="fa-solid fa-filter"></i></button>
                <a href="<?= e(url('auditoria')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr><th>#</th><th>Fecha y hora</th><th>Usuario</th><th>Acción</th><th>Tabla</th><th>ID</th><th class="d-none d-lg-table-cell">IP</th><th class="text-end"></th></tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="8" class="text-center text-body-secondary py-4">No hay registros con esos filtros.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $a): ?>
                    <tr>
                        <td class="small text-body-secondary"><?= e($a['id']) ?></td>
                        <td class="small text-nowrap"><?= e(fecha((string) $a['created_at'], true)) ?></td>
                        <td class="small"><?= e($a['usuario_nombre'] ?? $a['usuario_login'] ?? '—') ?><?php if ($a['usuario_nombre'] !== null && $a['usuario_login'] !== null): ?> <span class="text-body-secondary">(<?= e($a['usuario_login']) ?>)</span><?php endif; ?></td>
                        <td><span class="badge <?= e($claseAccion[$a['accion']] ?? 'text-bg-light') ?>"><?= e($a['accion']) ?></span></td>
                        <td class="small font-monospace"><?= e($a['tabla'] ?? '—') ?></td>
                        <td class="small"><?= e($a['registro_id'] ?? '—') ?></td>
                        <td class="small d-none d-lg-table-cell font-monospace"><?= e($a['ip'] ?? '') ?></td>
                        <td class="text-end">
                            <?php if ((int) $a['tiene_antes'] === 1 || (int) $a['tiene_despues'] === 1): ?>
                                <a href="<?= e(url('auditoria/' . $a['id'])) ?>" class="btn btn-sm btn-outline-secondary" title="Ver antes / después"><i class="fa-solid fa-code-compare"></i></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'auditoria', 'filtros' => $filtros]) ?>
    </div>
</div>
