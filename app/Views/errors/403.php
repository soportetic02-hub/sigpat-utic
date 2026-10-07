<?php

declare(strict_types=1);

/** @var string $mensaje */
?>
<div class="card auth-card shadow-lg border-0 text-center">
    <div class="card-body p-5">
        <div class="error-code text-warning">403</div>
        <h1 class="h4 mb-2">Acceso denegado</h1>
        <p class="text-body-secondary mb-4"><?= e($mensaje) ?></p>
        <a href="<?= e(url()) ?>" class="btn btn-primary"><i class="fa-solid fa-house me-1"></i>Ir al inicio</a>
    </div>
</div>
