<?php

declare(strict_types=1);

/**
 * Menú lateral. Cada ítem declara los roles que lo ven; las rutas siguen
 * protegidas por RolMiddleware aunque alguien escriba la URL directamente.
 *
 * @var array<string, mixed>|null $usuarioActual
 */
$menu = [
    [
        'seccion' => 'General',
        'items'   => [
            ['ruta' => '/', 'icono' => 'fa-gauge-high', 'texto' => 'Dashboard', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
        ],
    ],
    [
        'seccion' => 'Patrimonio',
        'items'   => [
            ['ruta' => 'equipos', 'icono' => 'fa-desktop', 'texto' => 'Inventario de equipos', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
            ['ruta' => 'licencias', 'icono' => 'fa-key', 'texto' => 'Licencias Microsoft 365', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
        ],
    ],
    [
        'seccion' => 'Soporte técnico',
        'items'   => [
            ['ruta' => 'mantenimientos', 'icono' => 'fa-screwdriver-wrench', 'texto' => 'Actas de mantenimiento', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
            ['ruta' => 'informes', 'icono' => 'fa-file-lines', 'texto' => 'Informes técnicos', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
        ],
    ],
    [
        'seccion' => 'Organización',
        'items'   => [
            ['ruta' => 'oficinas', 'icono' => 'fa-sitemap', 'texto' => 'Oficinas', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
            ['ruta' => 'personal', 'icono' => 'fa-id-card', 'texto' => 'Personal', 'roles' => ['ADMINISTRADOR', 'TECNICO']],
        ],
    ],
    [
        'seccion' => 'Administración',
        'items'   => [
            ['ruta' => 'usuarios', 'icono' => 'fa-users-gear', 'texto' => 'Usuarios del sistema', 'roles' => ['ADMINISTRADOR']],
            ['ruta' => 'parametros', 'icono' => 'fa-sliders', 'texto' => 'Parámetros y catálogos', 'roles' => ['ADMINISTRADOR']],
            ['ruta' => 'auditoria', 'icono' => 'fa-clipboard-list', 'texto' => 'Auditoría', 'roles' => ['ADMINISTRADOR']],
        ],
    ],
];
?>
<aside class="app-sidebar offcanvas-lg offcanvas-start" tabindex="-1" id="sidebar" aria-labelledby="sidebarTitulo">
    <div class="sidebar-brand d-flex align-items-center justify-content-between">
        <a href="<?= e(url()) ?>" class="d-flex align-items-center gap-2 text-decoration-none text-white">
            <span class="brand-icon"><i class="fa-solid fa-shield-halved"></i></span>
            <span>
                <span class="d-block fw-bold" id="sidebarTitulo">SIGPAT-OTIC</span>
                <span class="d-block small text-white-50">CAEN-EPG</span>
            </span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas"
                data-bs-target="#sidebar" aria-label="Cerrar menú"></button>
    </div>

    <nav class="sidebar-nav">
        <?php foreach ($menu as $bloque):
            $visibles = array_filter($bloque['items'], static fn (array $i): bool => has_role(...$i['roles']));
            if ($visibles === []) {
                continue;
            }
            ?>
            <div class="sidebar-section"><?= e($bloque['seccion']) ?></div>
            <?php foreach ($visibles as $item): ?>
                <a href="<?= e(url($item['ruta'])) ?>"
                   class="sidebar-link <?= is_active_path($item['ruta']) ? 'active' : '' ?>">
                    <i class="fa-solid <?= e($item['icono']) ?> fa-fw"></i>
                    <span><?= e($item['texto']) ?></span>
                </a>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>
</aside>
