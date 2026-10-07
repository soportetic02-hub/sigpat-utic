<?php

declare(strict_types=1);

$iconos = [
    'success' => 'fa-circle-check',
    'danger'  => 'fa-circle-exclamation',
    'warning' => 'fa-triangle-exclamation',
    'info'    => 'fa-circle-info',
];
foreach ($iconos as $tipo => $icono):
    $mensaje = flash($tipo);
    if ($mensaje === null || $mensaje === '') {
        continue;
    }
    ?>
    <div class="alert alert-<?= e($tipo) ?> alert-dismissible fade show d-flex align-items-start gap-2" role="alert">
        <i class="fa-solid <?= e($icono) ?> mt-1"></i>
        <div><?= e($mensaje) ?></div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endforeach; ?>
