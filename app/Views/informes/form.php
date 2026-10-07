<?php

declare(strict_types=1);

/**
 * Alta y edición de un informe técnico en borrador.
 *
 * @var array<string, mixed>|null $informe  null = nuevo
 * @var list<array<string, mixed>> $filas  equipos evaluados
 * @var array<string, string> $defecto
 * @var list<string> $acciones
 * @var list<string> $diagnosticos
 */
$esNuevo = $informe === null;
$v = static fn (string $c): string => old($c, (string) ($informe[$c] ?? $defecto[$c] ?? ''));
$cls = static fn (string $c, string $base = 'form-control'): string => $base . (error($c) !== null ? ' is-invalid' : '');
$accion = $esNuevo ? url('informes') : url('informes/' . $informe['id']);
$volver = $esNuevo ? url('informes') : url('informes/' . $informe['id']);
$deNombre = $esNuevo ? $defecto['de_nombre'] : (string) $informe['de_nombre'];
$deCargo = $esNuevo ? $defecto['de_cargo'] : (string) ($informe['de_cargo'] ?? '');
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-file-lines me-2 text-primary"></i><?= e($esNuevo ? 'Nuevo informe técnico' : 'Informe en borrador #' . $informe['id']) ?></h1>
    <a href="<?= e($volver) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<form method="post" action="<?= e($accion) ?>" novalidate id="form-informe">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" id="accion" value="guardar">

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-body-secondary mb-3">Encabezado</h2>
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="para_nombre" class="form-label">Para <span class="text-danger">*</span></label>
                    <input type="text" id="para_nombre" name="para_nombre" class="<?= e($cls('para_nombre')) ?>" maxlength="150" value="<?= e($v('para_nombre')) ?>">
                    <div class="invalid-feedback"><?= e(error('para_nombre')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="para_cargo" class="form-label">Cargo del destinatario <span class="text-danger">*</span></label>
                    <input type="text" id="para_cargo" name="para_cargo" class="<?= e($cls('para_cargo')) ?>" maxlength="150" value="<?= e($v('para_cargo')) ?>">
                    <div class="invalid-feedback"><?= e(error('para_cargo')) ?></div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">De</label>
                    <input type="text" class="form-control" value="<?= e(trim($deNombre . ($deCargo !== '' ? ' — ' . $deCargo : ''))) ?>" disabled>
                    <div class="form-text">Técnico que elabora el informe.</div>
                </div>
                <div class="col-md-3">
                    <label for="fecha" class="form-label">Fecha <span class="text-danger">*</span></label>
                    <input type="date" id="fecha" name="fecha" class="<?= e($cls('fecha')) ?>" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('fecha')) ?>">
                    <div class="invalid-feedback"><?= e(error('fecha')) ?></div>
                </div>
                <div class="col-md-3">
                    <label for="accion_requerida" class="form-label">Acción requerida <span class="text-danger">*</span></label>
                    <select id="accion_requerida" name="accion_requerida" class="<?= e($cls('accion_requerida', 'form-select')) ?>">
                        <option value="">— Seleccione —</option>
                        <?php foreach ($acciones as $a): ?>
                            <option value="<?= e($a) ?>" <?= $v('accion_requerida') === $a ? 'selected' : '' ?>><?= e(etiqueta($a)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('accion_requerida')) ?></div>
                </div>
                <div class="col-12">
                    <label for="asunto" class="form-label">Asunto <span class="text-danger">*</span></label>
                    <input type="text" id="asunto" name="asunto" class="<?= e($cls('asunto')) ?>" maxlength="255"
                           placeholder="p. ej. Evaluación técnica para la baja de equipos informáticos" value="<?= e($v('asunto')) ?>">
                    <div class="invalid-feedback"><?= e(error('asunto')) ?></div>
                </div>
                <div class="col-12">
                    <label for="antecedentes" class="form-label">Antecedentes</label>
                    <textarea id="antecedentes" name="antecedentes" class="<?= e($cls('antecedentes')) ?>" rows="3" maxlength="5000"><?= e($v('antecedentes')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('antecedentes')) ?></div>
                </div>
            </div>
            <div class="alert alert-danger small mt-3 mb-0 <?= $v('accion_requerida') === 'BAJA_DEFINITIVA' ? '' : 'd-none' ?>" id="aviso-baja">
                <i class="fa-solid fa-triangle-exclamation me-1"></i>Al <strong>emitir</strong> un informe con acción <strong>Baja definitiva</strong>,
                los equipos evaluados quedarán con recomendación de baja. La baja la confirma después un administrador desde la ficha de cada equipo.
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                <h2 class="h6 text-uppercase text-body-secondary mb-0">Equipos evaluados</h2>
                <div class="position-relative" style="min-width: 320px;">
                    <input type="search" id="buscar-equipo" class="form-control form-control-sm" autocomplete="off"
                           placeholder="Agregar equipo: serie, patrimonial, hostname, marca…" data-url="<?= e(url('informes/buscar-equipos')) ?>">
                    <div id="resultados-equipo" class="list-group position-absolute w-100 shadow-sm" style="z-index: 10;"></div>
                </div>
            </div>
            <?php if (error('equipos') !== null): ?><div class="alert alert-danger py-2 small"><?= e(error('equipos')) ?></div><?php endif; ?>

            <div id="lista-equipos">
                <?php foreach ($filas as $f): ?>
                    <?= \App\Core\View::partial('informes/_equipo', ['f' => $f, 'diagnosticos' => $diagnosticos]) ?>
                <?php endforeach; ?>
            </div>
            <p class="small text-body-secondary mb-0 <?= $filas === [] ? '' : 'd-none' ?>" id="sin-equipos">
                Aún no hay equipos. Use el buscador para agregar uno o varios.
            </p>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="conclusiones" class="form-label">Conclusiones <span class="small text-body-secondary">(obligatorias para emitir)</span></label>
                    <textarea id="conclusiones" name="conclusiones" class="<?= e($cls('conclusiones')) ?>" rows="4" maxlength="5000"><?= e($v('conclusiones')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('conclusiones')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="recomendaciones" class="form-label">Recomendaciones</label>
                    <textarea id="recomendaciones" name="recomendaciones" class="<?= e($cls('recomendaciones')) ?>" rows="4" maxlength="5000"><?= e($v('recomendaciones')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('recomendaciones')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar borrador</button>
        <?php if (!$esNuevo): ?>
            <button type="button" class="btn btn-success" id="btn-emitir"><i class="fa-solid fa-stamp me-1"></i>Guardar y emitir</button>
        <?php endif; ?>
    </div>
    <?php if ($esNuevo): ?>
        <p class="small text-body-secondary text-end">Después de guardar podrá adjuntar las evidencias fotográficas y emitir el informe.</p>
    <?php endif; ?>
</form>

<template id="tpl-equipo">
    <?= \App\Core\View::partial('informes/_equipo', ['f' => null, 'diagnosticos' => $diagnosticos]) ?>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-informe');
    var lista = document.getElementById('lista-equipos');
    var vacio = document.getElementById('sin-equipos');
    var input = document.getElementById('buscar-equipo');
    var resultados = document.getElementById('resultados-equipo');
    var temporizador = null;

    function actualizarVacio() {
        vacio.classList.toggle('d-none', lista.children.length > 0);
    }
    function yaAgregado(id) {
        return lista.querySelector('[data-equipo="' + id + '"]') !== null;
    }

    // Aviso de baja definitiva
    var accion = document.getElementById('accion_requerida');
    accion.addEventListener('change', function () {
        document.getElementById('aviso-baja').classList.toggle('d-none', accion.value !== 'BAJA_DEFINITIVA');
    });

    // Agregar un equipo desde el buscador, a partir de la plantilla
    function agregar(eq) {
        if (yaAgregado(eq.id)) {
            return;
        }
        var nodo = document.getElementById('tpl-equipo').content.firstElementChild.cloneNode(true);
        nodo.dataset.equipo = eq.id;
        nodo.querySelector('[data-campo="titulo"]').textContent = eq.tipo + ' ' + eq.marca + ' ' + eq.modelo;
        nodo.querySelector('[data-campo="detalle"]').textContent = 'Serie: ' + (eq.nro_serie || '—') + ' · Patrimonial: ' +
            (eq.codigo_patrimonial || '—') + ' · ' + eq.oficina_nombre + (eq.personal_nombre ? ' · ' + eq.personal_nombre : '');
        nodo.querySelector('input[name="equipos[]"]').value = eq.id;
        ['caracteristicas', 'estado_funcional', 'diagnostico'].forEach(function (c) {
            var area = nodo.querySelector('textarea[data-nombre="' + c + '"]');
            area.name = c + '[' + eq.id + ']';
            area.id = c + '-' + eq.id;
        });
        nodo.querySelector('textarea[data-nombre="caracteristicas"]').value = eq.caracteristicas_sugeridas || '';
        lista.appendChild(nodo);
        actualizarVacio();
    }

    input.addEventListener('input', function () {
        clearTimeout(temporizador);
        var q = input.value.trim();
        resultados.innerHTML = '';
        if (q.length < 2) {
            return;
        }
        temporizador = setTimeout(function () {
            SIGPAT.fetchJson(input.dataset.url + '?q=' + encodeURIComponent(q)).then(function (datos) {
                resultados.innerHTML = '';
                if (datos.equipos.length === 0) {
                    var nada = document.createElement('div');
                    nada.className = 'list-group-item small text-body-secondary';
                    nada.textContent = 'Sin resultados.';
                    resultados.appendChild(nada);
                    return;
                }
                datos.equipos.forEach(function (eq) {
                    var boton = document.createElement('button');
                    boton.type = 'button';
                    boton.className = 'list-group-item list-group-item-action small';
                    boton.disabled = yaAgregado(eq.id);
                    boton.textContent = eq.tipo + ' ' + eq.marca + ' ' + eq.modelo + ' · ' + (eq.nro_serie || eq.codigo_patrimonial || '') +
                        (boton.disabled ? ' (ya agregado)' : '');
                    boton.addEventListener('click', function () {
                        agregar(eq);
                        resultados.innerHTML = '';
                        input.value = '';
                    });
                    resultados.appendChild(boton);
                });
            }).catch(function (error) {
                resultados.innerHTML = '';
                var fallo = document.createElement('div');
                fallo.className = 'list-group-item small text-danger';
                fallo.textContent = error.message;
                resultados.appendChild(fallo);
            });
        }, 300);
    });

    // Quitar equipo y frases rápidas de diagnóstico (delegación de eventos)
    lista.addEventListener('click', function (evento) {
        var quitar = evento.target.closest('[data-quitar-equipo]');
        if (quitar) {
            quitar.closest('[data-equipo]').remove();
            actualizarVacio();
            return;
        }
        var frase = evento.target.closest('[data-frase]');
        if (frase) {
            var area = frase.closest('[data-equipo]').querySelector('textarea[data-nombre="diagnostico"]');
            area.value = area.value.trim() === '' ? frase.dataset.frase : area.value.trim() + '\n' + frase.dataset.frase;
            area.focus();
        }
    });

    var emitir = document.getElementById('btn-emitir');
    if (emitir) {
        emitir.addEventListener('click', function () {
            if (!window.confirm('¿Guardar y emitir el informe? Recibirá su número correlativo y ya no podrá modificarse.')) {
                return;
            }
            document.getElementById('accion').value = 'emitir';
            form.requestSubmit();
        });
    }
});
</script>
