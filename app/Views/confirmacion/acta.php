<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Página pública del enlace del correo: ver el acta y confirmar su recepción.
 *
 * @var array<string, mixed> $envio
 * @var string $token
 */
$recibido = $envio['estado'] === 'RECIBIDO';
$vencido = (bool) $envio['vencido'];
?>
<div class="card auth-card shadow-lg border-0" style="max-width: 560px;">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="auth-logo mb-3"><i class="fa-solid fa-file-signature"></i></div>
            <h1 class="h5 fw-bold mb-1">Acta de mantenimiento N° <?= e($envio['numero']) ?></h1>
            <p class="text-body-secondary small mb-0">Oficina de Tecnologías de la Información · CAEN-EPG</p>
        </div>

        <?= View::partial('partials/flash') ?>

        <dl class="row small mb-4">
            <dt class="col-4">Usuario</dt><dd class="col-8"><?= e($envio['personal_nombre'] ?? '—') ?></dd>
            <dt class="col-4">Oficina</dt><dd class="col-8"><?= e($envio['oficina_nombre']) ?></dd>
            <dt class="col-4">Equipo</dt><dd class="col-8"><?= e(etiqueta((string) $envio['equipo_tipo']) . ' ' . $envio['marca'] . ' ' . $envio['modelo']) ?><?= $envio['nro_serie'] !== null ? '<br><span class="text-body-secondary">Serie ' . e($envio['nro_serie']) . '</span>' : '' ?></dd>
            <dt class="col-4">Mantenimiento</dt><dd class="col-8"><?= e(etiqueta((string) $envio['tipo'])) ?> · <?= e(fecha((string) $envio['fecha_salida'], true)) ?></dd>
            <dt class="col-4">Técnico</dt><dd class="col-8"><?= e($envio['tecnico_nombre']) ?></dd>
            <dt class="col-4">Firma digital</dt><dd class="col-8"><?= $envio['firmado_at'] !== null ? '<i class="fa-solid fa-circle-check text-success me-1"></i>Firmada por el Jefe de la OTIC' : 'Sin firma digital' ?></dd>
        </dl>

        <a href="<?= e(url('actas/confirmar/' . $token . '/pdf')) ?>" class="btn btn-outline-primary w-100 mb-3" target="_blank" rel="noopener">
            <i class="fa-solid fa-file-pdf me-2"></i>Ver el acta (PDF)
        </a>

        <?php if ($recibido): ?>
            <div class="alert alert-success mb-0 d-flex gap-2">
                <i class="fa-solid fa-circle-check mt-1"></i>
                <div>Recepción confirmada el <?= e(fecha((string) $envio['recibido_at'], true)) ?>. No necesita hacer nada más.</div>
            </div>
        <?php elseif ($vencido): ?>
            <div class="alert alert-warning mb-0 d-flex gap-2">
                <i class="fa-solid fa-clock mt-1"></i>
                <div>Este enlace venció el <?= e(fecha((string) $envio['expira_at'], true)) ?>. Solicite a la OTIC que le reenvíe el acta.</div>
            </div>
        <?php else: ?>
            <form method="post" action="<?= e(url('actas/confirmar/' . $token)) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success w-100 py-2">
                    <i class="fa-solid fa-check me-2"></i>Confirmo que recibí y revisé el acta
                </button>
            </form>
            <p class="text-body-secondary small text-center mt-3 mb-0">
                Si encuentra algún error en el acta, no confirme y comuníquese con la OTIC.
            </p>
        <?php endif; ?>
    </div>
</div>
