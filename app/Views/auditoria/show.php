<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $registro
 * @var list<array{campo: string, antes: string, despues: string, estado: string}> $diferencias
 */
$cambios = array_filter($diferencias, static fn (array $d): bool => $d['estado'] !== 'igual');
$marca = ['modificado' => 'table-warning', 'agregado' => 'table-success', 'quitado' => 'table-danger', 'igual' => ''];
$etiquetaEstado = ['modificado' => 'Modificado', 'agregado' => 'Agregado', 'quitado' => 'Quitado', 'igual' => ''];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-code-compare me-2 text-primary"></i>Registro de auditoría #<?= e($registro['id']) ?></h1>
    <a href="<?= e(url('auditoria')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body small">
        <dl class="row mb-0">
            <dt class="col-sm-2">Fecha y hora</dt><dd class="col-sm-4"><?= e(fecha((string) $registro['created_at'], true)) ?></dd>
            <dt class="col-sm-2">Acción</dt><dd class="col-sm-4"><span class="badge text-bg-primary"><?= e($registro['accion']) ?></span></dd>
            <dt class="col-sm-2">Usuario</dt><dd class="col-sm-4"><?= e($registro['usuario_nombre'] ?? '—') ?><?= $registro['usuario_login'] !== null ? ' (' . e($registro['usuario_login']) . ')' : '' ?></dd>
            <dt class="col-sm-2">Tabla / ID</dt><dd class="col-sm-4 font-monospace"><?= e(($registro['tabla'] ?? '—') . ' / ' . ($registro['registro_id'] ?? '—')) ?></dd>
            <dt class="col-sm-2">IP</dt><dd class="col-sm-4 font-monospace"><?= e($registro['ip'] ?? '—') ?></dd>
            <dt class="col-sm-2">Navegador</dt><dd class="col-sm-4 text-break"><?= e($registro['user_agent'] ?? '—') ?></dd>
        </dl>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <h2 class="h6 text-uppercase text-body-secondary mb-0">Antes / después (<?= e(count($cambios)) ?> campo(s) con cambios)</h2>
            <?php if ($cambios !== [] && count($cambios) < count($diferencias)): ?>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" role="switch" id="solo-cambios" checked>
                    <label class="form-check-label small" for="solo-cambios">Mostrar solo los cambios</label>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($diferencias === []): ?>
            <p class="small text-body-secondary mb-0">Este registro no guarda datos.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-top mb-0" id="tabla-diferencias">
                    <thead class="table-light"><tr><th style="width: 18%;">Campo</th><th style="width: 36%;">Antes</th><th style="width: 36%;">Después</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($diferencias as $d): ?>
                        <tr class="<?= e($marca[$d['estado']]) ?>" data-estado="<?= e($d['estado']) ?>">
                            <td class="small font-monospace"><?= e($d['campo']) ?></td>
                            <td class="small"><pre class="mb-0 small text-wrap"><?= e($d['antes']) ?></pre></td>
                            <td class="small"><pre class="mb-0 small text-wrap"><?= e($d['despues']) ?></pre></td>
                            <td class="small text-nowrap"><?= e($etiquetaEstado[$d['estado']]) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var interruptor = document.getElementById('solo-cambios');
    if (!interruptor) {
        return;
    }
    function aplicar() {
        document.querySelectorAll('#tabla-diferencias tbody tr').forEach(function (fila) {
            fila.classList.toggle('d-none', interruptor.checked && fila.dataset.estado === 'igual');
        });
    }
    interruptor.addEventListener('change', aplicar);
    aplicar();
});
</script>
