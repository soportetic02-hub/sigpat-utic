<?php

declare(strict_types=1);

/**
 * @var int $status
 * @var string $mensaje
 * @var Throwable|null $detalle  Solo con APP_ENV=local
 */
?>
<div class="card auth-card auth-card-wide shadow-lg border-0 text-center">
    <div class="card-body p-5">
        <div class="error-code text-danger"><?= e($status) ?></div>
        <h1 class="h4 mb-2"><?= e($status === 500 ? 'Error del servidor' : 'No se pudo completar la solicitud') ?></h1>
        <p class="text-body-secondary mb-4"><?= e($mensaje) ?></p>
        <a href="<?= e(url()) ?>" class="btn btn-primary"><i class="fa-solid fa-house me-1"></i>Ir al inicio</a>

        <?php if ($detalle !== null): ?>
            <div class="text-start mt-4">
                <div class="alert alert-secondary small mb-0">
                    <strong>Detalle (solo visible con APP_ENV=local):</strong><br>
                    <?= e($detalle::class) ?>: <?= e($detalle->getMessage()) ?><br>
                    <?= e($detalle->getFile()) ?>:<?= e($detalle->getLine()) ?>
                    <pre class="mt-2 mb-0 small" style="white-space: pre-wrap;"><?= e($detalle->getTraceAsString()) ?></pre>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
