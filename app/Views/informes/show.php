<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $informe
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $evidencias
 * @var bool $puedeAnular
 * @var int $maxEnvio
 */
$id = (int) $informe['id'];
$emitido = $informe['estado'] === 'EMITIDO';
$claseAccion = ['REPARACION' => 'text-bg-info', 'INOPERATIVIDAD' => 'text-bg-warning', 'BAJA_DEFINITIVA' => 'text-bg-danger', 'REEMPLAZO' => 'text-bg-primary'];
$texto = static fn (?string $t): string => $t === null || trim($t) === '' ? '<span class="text-body-secondary">—</span>' : nl2br(e($t));
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><i class="fa-solid fa-file-lines me-2 text-primary"></i><?= $emitido ? 'Informe técnico N° ' . e($informe['numero']) : 'Informe en borrador #' . e($id) ?></h1>
        <span class="badge <?= $emitido ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($informe['estado'])) ?></span>
        <span class="badge <?= e($claseAccion[$informe['accion_requerida']] ?? 'text-bg-light') ?>"><?= e(etiqueta($informe['accion_requerida'])) ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(url('informes')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <a href="<?= e(url('informes/' . $id . '/pdf')) ?>" class="btn btn-outline-danger" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i>Vista previa PDF</a>
        <?php if ($emitido): ?>
            <a href="<?= e(url('informes/' . $id . '/descargar')) ?>" class="btn btn-danger"><i class="fa-solid fa-file-pdf me-1"></i>Descargar PDF</a>
            <a href="<?= e(url('informes/' . $id . '/word')) ?>" class="btn btn-primary"><i class="fa-solid fa-file-word me-1"></i>Descargar Word</a>
        <?php else: ?>
            <a href="<?= e(url('informes/' . $id . '/editar')) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Editar</a>
            <form method="post" action="<?= e(url('informes/' . $id . '/emitir')) ?>"
                  data-confirm="¿Emitir el informe? Recibirá su número correlativo y ya no podrá modificarse.<?= $informe['accion_requerida'] === 'BAJA_DEFINITIVA' ? ' Los equipos quedarán con recomendación de baja.' : '' ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-success"><i class="fa-solid fa-stamp me-1"></i>Emitir</button>
            </form>
            <?php if ($puedeAnular): ?>
                <form method="post" action="<?= e(url('informes/' . $id . '/anular')) ?>" data-confirm="¿Anular este borrador? Se eliminará junto con sus fotos.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger" title="Anular borrador"><i class="fa-solid fa-trash"></i></button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if ($emitido && $informe['accion_requerida'] === 'BAJA_DEFINITIVA'): ?>
    <div class="alert alert-danger small">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>Los equipos de este informe quedaron con <strong>recomendación de baja</strong>.
        Un administrador debe confirmar la baja desde la ficha de cada equipo.
    </div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body small">
                <dl class="row mb-0">
                    <dt class="col-sm-2">Para</dt><dd class="col-sm-10"><?= e($informe['para_nombre']) ?> — <?= e($informe['para_cargo']) ?></dd>
                    <dt class="col-sm-2">De</dt><dd class="col-sm-10"><?= e($informe['de_nombre']) ?><?= $informe['de_cargo'] !== null ? ' — ' . e($informe['de_cargo']) : '' ?></dd>
                    <dt class="col-sm-2">Asunto</dt><dd class="col-sm-10"><?= e($informe['asunto']) ?></dd>
                    <dt class="col-sm-2">Fecha</dt><dd class="col-sm-10"><?= e(fecha((string) $informe['fecha'])) ?></dd>
                    <?php if ($emitido): ?>
                        <dt class="col-sm-2">Emitido</dt><dd class="col-sm-10"><?= e(fecha((string) $informe['emitido_at'], true)) ?> por <?= e($informe['emitido_por_nombre'] ?? '') ?></dd>
                    <?php endif; ?>
                    <dt class="col-sm-2">Antecedentes</dt><dd class="col-sm-10 mb-0"><?= $texto($informe['antecedentes']) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Equipos evaluados (<?= e(count($equipos)) ?>)</h2>
                <?php foreach ($equipos as $n => $eq): ?>
                    <div class="border rounded p-3 mb-2 small">
                        <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
                            <div>
                                <span class="fw-semibold"><?= e(($n + 1) . '. ' . etiqueta((string) $eq['tipo']) . ' ' . $eq['marca'] . ' ' . $eq['modelo']) ?></span>
                                <span class="text-body-secondary">· Serie <?= e($eq['nro_serie'] ?? '—') ?> · Patrimonial <?= e($eq['codigo_patrimonial'] ?? '—') ?></span>
                            </div>
                            <div>
                                <a href="<?= e(url('equipos/' . $eq['equipo_id'])) ?>" class="small">Ver ficha</a>
                                <?php if ((int) $eq['recomendado_baja'] === 1 && $eq['estado_operativo'] !== 'DE_BAJA'): ?><span class="badge text-bg-danger ms-1">Recomendado para baja</span><?php endif; ?>
                                <?php if ($eq['estado_operativo'] === 'DE_BAJA'): ?><span class="badge text-bg-dark ms-1">De baja</span><?php endif; ?>
                            </div>
                        </div>
                        <div class="row g-2">
                            <div class="col-md-4"><div class="text-body-secondary">Características</div><?= $texto($eq['caracteristicas']) ?></div>
                            <div class="col-md-4"><div class="text-body-secondary">Estado funcional</div><?= $texto($eq['estado_funcional']) ?></div>
                            <div class="col-md-4"><div class="text-body-secondary">Diagnóstico técnico</div><?= $texto($eq['diagnostico']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100"><div class="card-body small">
            <h2 class="h6 text-uppercase text-body-secondary mb-2">Conclusiones</h2>
            <p class="mb-0"><?= $texto($informe['conclusiones']) ?></p>
        </div></div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100"><div class="card-body small">
            <h2 class="h6 text-uppercase text-body-secondary mb-2">Recomendaciones</h2>
            <p class="mb-0"><?= $texto($informe['recomendaciones']) ?></p>
        </div></div>
    </div>

    <div class="col-12" id="evidencias">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-camera me-1"></i>Evidencias fotográficas (<?= e(count($evidencias)) ?>)</h2>

                <?php if ($evidencias === []): ?>
                    <p class="small text-body-secondary">Sin fotos adjuntas.</p>
                <?php else: ?>
                    <div class="row g-3 mb-3">
                        <?php foreach ($evidencias as $ev): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100">
                                    <a href="<?= e(url('evidencias/' . $ev['id'])) ?>" target="_blank" rel="noopener">
                                        <img src="<?= e(url('evidencias/' . $ev['id'])) ?>" class="card-img-top evidencia-miniatura" alt="<?= e($ev['descripcion'] ?? 'Evidencia') ?>" loading="lazy">
                                    </a>
                                    <div class="card-body p-2 small">
                                        <div><?= e($ev['descripcion'] ?? 'Sin descripción') ?></div>
                                        <?php if ($ev['marca'] !== null): ?><div class="text-body-secondary"><?= e($ev['marca'] . ' ' . $ev['modelo']) ?></div><?php endif; ?>
                                    </div>
                                    <?php if (!$emitido): ?>
                                        <div class="card-footer bg-transparent p-2">
                                            <form method="post" action="<?= e(url('informes/' . $id . '/evidencias/' . $ev['id'] . '/eliminar')) ?>" data-confirm="¿Eliminar esta foto?">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn btn-sm btn-outline-danger w-100"><i class="fa-solid fa-trash me-1"></i>Eliminar</button>
                                            </form>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (!$emitido): ?>
                    <form method="post" action="<?= e(url('informes/' . $id . '/evidencias')) ?>" enctype="multipart/form-data" id="form-evidencias" class="border rounded p-3 bg-body-tertiary">
                        <?= csrf_field() ?>
                        <label for="fotos" class="form-label">Agregar fotos (JPG o PNG, máximo 5 MB cada una, hasta <?= e($maxEnvio) ?> por envío)</label>
                        <input type="file" id="fotos" name="fotos[]" class="form-control mb-2" accept="image/jpeg,image/png" multiple>
                        <div class="alert alert-danger small py-2 d-none" id="error-fotos"></div>
                        <div id="detalle-fotos"></div>
                        <button type="submit" class="btn btn-primary btn-sm" id="btn-subir" disabled><i class="fa-solid fa-upload me-1"></i>Subir fotos</button>
                    </form>
                    <template id="tpl-foto">
                        <div class="row g-2 align-items-center mb-2">
                            <div class="col-md-4 small text-truncate" data-campo="nombre"></div>
                            <div class="col-md-5"><input type="text" class="form-control form-control-sm" maxlength="255" placeholder="Descripción de la foto" data-campo="descripcion"></div>
                            <div class="col-md-3">
                                <select class="form-select form-select-sm" data-campo="equipo">
                                    <option value="">— Equipo (opcional) —</option>
                                    <?php foreach ($equipos as $eq): ?>
                                        <option value="<?= e($eq['equipo_id']) ?>"><?= e($eq['marca'] . ' ' . $eq['modelo'] . ' · ' . ($eq['nro_serie'] ?? '')) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </template>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!$emitido): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('fotos');
    var detalle = document.getElementById('detalle-fotos');
    var error = document.getElementById('error-fotos');
    var boton = document.getElementById('btn-subir');
    var maximo = <?= (int) $maxEnvio ?>;
    var limite = 5 * 1024 * 1024;

    input.addEventListener('change', function () {
        detalle.innerHTML = '';
        error.classList.add('d-none');
        var archivos = Array.prototype.slice.call(input.files);
        var problemas = [];
        if (archivos.length > maximo) {
            problemas.push('Seleccione como máximo ' + maximo + ' fotos.');
        }
        archivos.forEach(function (archivo, i) {
            if (['image/jpeg', 'image/png'].indexOf(archivo.type) === -1) {
                problemas.push(archivo.name + ': solo JPG o PNG.');
            } else if (archivo.size > limite) {
                problemas.push(archivo.name + ': supera 5 MB.');
            }
            var fila = document.getElementById('tpl-foto').content.firstElementChild.cloneNode(true);
            fila.querySelector('[data-campo="nombre"]').textContent = archivo.name;
            fila.querySelector('[data-campo="descripcion"]').name = 'descripciones[' + i + ']';
            fila.querySelector('[data-campo="equipo"]').name = 'equipos_foto[' + i + ']';
            detalle.appendChild(fila);
        });
        if (problemas.length > 0) {
            error.textContent = problemas.join(' ');
            error.classList.remove('d-none');
        }
        boton.disabled = archivos.length === 0 || problemas.length > 0;
    });
});
</script>
<?php endif; ?>
