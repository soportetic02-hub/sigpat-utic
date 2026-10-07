<?php

declare(strict_types=1);

/**
 * @var array<string, mixed>|null $usuarioActual
 * @var string $titulo
 */
$nombre = $usuarioActual === null ? '' : trim($usuarioActual['nombres'] . ' ' . $usuarioActual['apellidos']);
$rol = (string) ($usuarioActual['rol'] ?? '');
?>
<header class="app-navbar navbar navbar-expand bg-body border-bottom px-3">
    <button class="btn btn-link text-body d-lg-none me-2 p-1" type="button" data-bs-toggle="offcanvas"
            data-bs-target="#sidebar" aria-controls="sidebar" aria-label="Abrir menú">
        <i class="fa-solid fa-bars fa-lg"></i>
    </button>
    <span class="navbar-text fw-semibold text-truncate"><?= e($titulo) ?></span>

    <?php if ($usuarioActual !== null): ?>
        <div class="ms-auto dropdown">
            <button class="btn btn-link text-decoration-none text-body dropdown-toggle d-flex align-items-center gap-2"
                    type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="avatar"><i class="fa-solid fa-user"></i></span>
                <span class="d-none d-sm-inline text-start lh-sm">
                    <span class="d-block small fw-semibold"><?= e($nombre) ?></span>
                    <span class="badge <?= $rol === 'ADMINISTRADOR' ? 'text-bg-primary' : 'text-bg-secondary' ?>"><?= e($rol) ?></span>
                </span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header"><?= e($usuarioActual['usuario']) ?></h6></li>
                <li>
                    <a class="dropdown-item" href="<?= e(url('perfil/password')) ?>">
                        <i class="fa-solid fa-key fa-fw me-2"></i>Cambiar contraseña
                    </a>
                </li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <form method="post" action="<?= e(url('logout')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="dropdown-item text-danger">
                            <i class="fa-solid fa-right-from-bracket fa-fw me-2"></i>Cerrar sesión
                        </button>
                    </form>
                </li>
            </ul>
        </div>
    <?php endif; ?>
</header>
