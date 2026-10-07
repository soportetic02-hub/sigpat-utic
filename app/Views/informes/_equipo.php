<?php

declare(strict_types=1);

/**
 * Tarjeta de un equipo evaluado dentro del formulario del informe.
 * Con $f = null se genera la plantilla vacía que usa JavaScript.
 *
 * @var array<string, mixed>|null $f
 * @var list<string> $diagnosticos
 */
$id = $f === null ? '' : (string) $f['equipo_id'];
$nombre = static fn (string $campo): string => $f === null ? '' : $campo . '[' . $id . ']';
?>
<div class="border rounded p-3 mb-3" data-equipo="<?= e($id) ?>">
    <input type="hidden" name="equipos[]" value="<?= e($id) ?>">
    <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <div>
            <div class="fw-semibold" data-campo="titulo"><?= $f === null ? '' : e(etiqueta((string) $f['tipo']) . ' ' . $f['marca'] . ' ' . $f['modelo']) ?></div>
            <div class="small text-body-secondary" data-campo="detalle">
                <?php if ($f !== null): ?>
                    Serie: <?= e($f['nro_serie'] ?? '—') ?> · Patrimonial: <?= e($f['codigo_patrimonial'] ?? '—') ?> · <?= e($f['oficina_nombre']) ?><?= ($f['personal_nombre'] ?? null) !== null ? ' · ' . e($f['personal_nombre']) : '' ?>
                <?php endif; ?>
            </div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger" data-quitar-equipo title="Quitar del informe"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="row g-2">
        <div class="col-md-6">
            <label class="form-label small mb-1">Características</label>
            <textarea class="form-control form-control-sm" rows="2" maxlength="3000" data-nombre="caracteristicas"
                      name="<?= e($nombre('caracteristicas')) ?>"><?= $f === null ? '' : e((string) ($f['caracteristicas'] ?? '')) ?></textarea>
        </div>
        <div class="col-md-6">
            <label class="form-label small mb-1">Estado funcional</label>
            <textarea class="form-control form-control-sm" rows="2" maxlength="3000" data-nombre="estado_funcional"
                      placeholder="p. ej. No enciende; enciende con fallas intermitentes…"
                      name="<?= e($nombre('estado_funcional')) ?>"><?= $f === null ? '' : e((string) ($f['estado_funcional'] ?? '')) ?></textarea>
        </div>
        <div class="col-12">
            <label class="form-label small mb-1">Diagnóstico técnico <span class="text-body-secondary">(obligatorio para emitir)</span></label>
            <div class="d-flex flex-wrap gap-1 mb-1">
                <?php foreach ($diagnosticos as $frase): ?>
                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 small" data-frase="<?= e($frase) ?>"
                            title="Agregar al diagnóstico"><?= e(mb_strimwidth($frase, 0, 38, '…')) ?></button>
                <?php endforeach; ?>
            </div>
            <textarea class="form-control form-control-sm" rows="3" maxlength="3000" data-nombre="diagnostico"
                      name="<?= e($nombre('diagnostico')) ?>"><?= $f === null ? '' : e((string) ($f['diagnostico'] ?? '')) ?></textarea>
        </div>
    </div>
</div>
