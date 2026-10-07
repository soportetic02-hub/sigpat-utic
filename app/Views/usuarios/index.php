<?php

declare(strict_types=1);

use App\Core\View;

/**
 * @var array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int} $pagina
 * @var array{q: string, rol: string, estado: string} $filtros
 * @var list<string> $roles
 */
$idActual = auth_user()['id'] ?? null;
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-users-gear me-2 text-primary"></i>Usuarios del sistema</h1>
    <a href="<?= e(url('usuarios/crear')) ?>" class="btn btn-primary">
        <i class="fa-solid fa-user-plus me-1"></i>Nuevo usuario
    </a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" action="<?= e(url('usuarios')) ?>" class="row g-2 align-items-end mb-3">
            <div class="col-12 col-md-5">
                <label for="q" class="form-label small mb-1">Buscar</label>
                <input type="search" id="q" name="q" class="form-control" maxlength="100"
                       placeholder="Usuario, nombres, apellidos, DNI o correo" value="<?= e($filtros['q']) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="rol" class="form-label small mb-1">Rol</label>
                <select id="rol" name="rol" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= e($r) ?>" <?= $filtros['rol'] === $r ? 'selected' : '' ?>><?= e($r) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label for="estado" class="form-label small mb-1">Estado</label>
                <select id="estado" name="estado" class="form-select">
                    <option value="">Todos</option>
                    <option value="activos" <?= $filtros['estado'] === 'activos' ? 'selected' : '' ?>>Activos</option>
                    <option value="inactivos" <?= $filtros['estado'] === 'inactivos' ? 'selected' : '' ?>>Inactivos</option>
                </select>
            </div>
            <div class="col-12 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary flex-fill"><i class="fa-solid fa-magnifying-glass me-1"></i>Filtrar</button>
                <a href="<?= e(url('usuarios')) ?>" class="btn btn-outline-secondary" title="Limpiar filtros"><i class="fa-solid fa-eraser"></i></a>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Usuario</th>
                        <th>Nombre</th>
                        <th class="d-none d-md-table-cell">Cargo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th class="d-none d-lg-table-cell">Último acceso</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($pagina['data'] === []): ?>
                    <tr><td colspan="7" class="text-center text-body-secondary py-4">No se encontraron usuarios.</td></tr>
                <?php endif; ?>
                <?php foreach ($pagina['data'] as $u):
                    $bloqueado = $u['bloqueado_hasta'] !== null && strtotime((string) $u['bloqueado_hasta']) > time();
                    $esPropio = (int) $u['id'] === $idActual;
                    ?>
                    <tr class="<?= (int) $u['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                        <td class="fw-semibold"><?= e($u['usuario']) ?></td>
                        <td>
                            <?= e($u['apellidos'] . ', ' . $u['nombres']) ?>
                            <?php if ($u['email'] !== null): ?><div class="small text-body-secondary"><?= e($u['email']) ?></div><?php endif; ?>
                        </td>
                        <td class="d-none d-md-table-cell"><?= e($u['cargo']) ?></td>
                        <td>
                            <span class="badge <?= $u['rol'] === 'ADMINISTRADOR' ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= e($u['rol']) ?></span>
                            <?php if ((int) $u['puede_firmar'] === 1): ?><span class="badge text-bg-success" title="Puede firmar actas con su DNIe"><i class="fa-solid fa-signature"></i></span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ((int) $u['activo'] === 1): ?>
                                <span class="badge text-bg-success">Activo</span>
                            <?php else: ?>
                                <span class="badge text-bg-light border">Inactivo</span>
                            <?php endif; ?>
                            <?php if ($bloqueado): ?>
                                <span class="badge text-bg-warning" title="Bloqueado hasta <?= e(fecha((string) $u['bloqueado_hasta'], true)) ?>">
                                    <i class="fa-solid fa-lock"></i> Bloqueado
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-lg-table-cell small"><?= e($u['ultimo_acceso'] !== null ? fecha((string) $u['ultimo_acceso'], true) : 'Nunca') ?></td>
                        <td class="text-end text-nowrap">
                            <a href="<?= e(url('usuarios/' . $u['id'] . '/editar')) ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                <i class="fa-solid fa-pen"></i>
                            </a>
                            <a href="<?= e(url('usuarios/' . $u['id'] . '/password')) ?>" class="btn btn-sm btn-outline-secondary" title="Restablecer contraseña">
                                <i class="fa-solid fa-key"></i>
                            </a>
                            <?php if (!$esPropio): ?>
                                <form method="post" action="<?= e(url('usuarios/' . $u['id'] . '/activo')) ?>" class="d-inline"
                                      data-confirm="<?= e((int) $u['activo'] === 1 ? '¿Desactivar al usuario ' . $u['usuario'] . '? No podrá iniciar sesión.' : '¿Activar al usuario ' . $u['usuario'] . '?') ?>">
                                    <?= csrf_field() ?>
                                    <?php if ((int) $u['activo'] === 1): ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar"><i class="fa-solid fa-user-slash"></i></button>
                                    <?php else: ?>
                                        <button type="submit" class="btn btn-sm btn-outline-success" title="Activar"><i class="fa-solid fa-user-check"></i></button>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?= View::partial('partials/pagination', ['pagina' => $pagina, 'ruta' => 'usuarios', 'filtros' => $filtros]) ?>
    </div>
</div>
