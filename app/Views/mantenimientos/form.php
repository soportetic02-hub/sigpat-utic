<?php

declare(strict_types=1);

/**
 * Paso 2: formulario del acta (alta o edición de un borrador).
 *
 * @var array<string, mixed> $equipo  ficha del equipo (v_equipos_estado)
 * @var array<string, mixed>|null $acta  null = acta nueva
 * @var array<string, list<array<string, mixed>>> $checklist  ítems aplicables por categoría
 * @var list<int> $checklistSel
 * @var array<string, array{accion: string, detalle: ?string}> $componentes
 * @var array<int, string> $softwareSel  software_id => versión
 * @var list<array<string, mixed>> $catalogo
 * @var array<string, mixed>|null $licencia
 * @var list<array{id: int, nombre: string, rol: string}> $tecnicos  (solo ADMINISTRADOR)
 * @var list<string> $tipos
 * @var list<string> $listaComponentes
 * @var list<string> $acciones
 * @var list<string> $estadosFinales
 * @var list<string> $condiciones
 */
$esNueva = $acta === null;
$conOld = hay_old();
$dt = static fn (?string $v): string => $v === null || $v === '' ? '' : date('Y-m-d\TH:i', (int) strtotime($v));
$v = static fn (string $c, string $def = ''): string => old($c, $def);

$tipoSel = $v('tipo', (string) ($acta['tipo'] ?? 'PREVENTIVO'));
$ingreso = $v('fecha_ingreso', $esNueva ? date('Y-m-d\TH:i') : $dt($acta['fecha_ingreso']));
$salida = $v('fecha_salida', $esNueva ? '' : $dt($acta['fecha_salida']));
$marcados = $conOld ? array_map('intval', old_array('checklist')) : $checklistSel;
$compAccion = $conOld ? old_array('comp_accion') : array_map(static fn (array $c): string => $c['accion'], $componentes);
$compDetalle = $conOld ? old_array('comp_detalle') : array_map(static fn (array $c): string => (string) $c['detalle'], $componentes);
$softMarcado = $conOld ? array_map('intval', old_array('software')) : array_keys($softwareSel);
$softVersion = $conOld ? old_array('software_version') : $softwareSel;
$tecnicoSel = $v('tecnico_id', (string) ($acta['tecnico_id'] ?? auth_user()['id']));
$licenciaAccion = $v('licencia_accion');
$nombresCat = ['FISICO' => ['Físico', 'fa-broom'], 'LOGICO' => ['Lógico', 'fa-laptop-code'], 'RED' => ['Red', 'fa-network-wired']];
$accion = $esNueva ? url('mantenimientos') : url('mantenimientos/' . $acta['id']);
$esImpresora = $equipo['tipo'] === 'IMPRESORA';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0">
        <i class="fa-solid fa-screwdriver-wrench me-2 text-primary"></i><?= e($esNueva ? 'Nueva acta de mantenimiento' : 'Acta en borrador #' . $acta['id']) ?>
    </h1>
    <a href="<?= e($esNueva ? url('mantenimientos/crear') : url('mantenimientos/' . $acta['id'])) ?>" class="btn btn-outline-secondary">
        <i class="fa-solid fa-arrow-left me-1"></i><?= $esNueva ? 'Cambiar equipo' : 'Volver' ?>
    </a>
</div>

<form method="post" action="<?= e($accion) ?>" novalidate id="form-acta">
    <?= csrf_field() ?>
    <input type="hidden" name="accion" id="accion" value="borrador">
    <?php if ($esNueva): ?><input type="hidden" name="equipo_id" value="<?= e($equipo['id']) ?>"><?php endif; ?>

    <!-- Equipo y usuario (precargados) -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3 small">
                <div class="col-md-6">
                    <h2 class="h6 text-uppercase text-body-secondary mb-2">Equipo</h2>
                    <div class="fw-semibold fs-6"><?= e(etiqueta($equipo['tipo']) . ' ' . $equipo['marca'] . ' ' . $equipo['modelo']) ?></div>
                    <div>Serie: <strong><?= e($equipo['nro_serie'] ?? '—') ?></strong> · Patrimonial: <strong><?= e($equipo['codigo_patrimonial'] ?? '—') ?></strong></div>
                    <div>Hostname: <strong><?= e($equipo['hostname'] ?? '—') ?></strong> · IP: <strong><?= e($equipo['ip_lan'] ?? '—') ?></strong></div>
                </div>
                <div class="col-md-6">
                    <h2 class="h6 text-uppercase text-body-secondary mb-2">Usuario y oficina</h2>
                    <?php if ($esNueva): ?>
                        <div class="fw-semibold fs-6"><?= e($equipo['personal_nombre'] ?? 'Sin responsable') ?></div>
                        <div><?= e($equipo['personal_cargo'] ?? '') ?></div>
                        <div>Oficina: <strong><?= e($equipo['oficina_nombre']) ?></strong></div>
                    <?php else: ?>
                        <div class="fw-semibold fs-6"><?= e($acta['personal_nombres'] !== null ? $acta['personal_nombres'] . ' ' . $acta['personal_apellidos'] : 'Sin responsable') ?></div>
                        <div><?= e($acta['personal_cargo'] ?? '') ?></div>
                        <div>Oficina: <strong><?= e($acta['oficina_nombre']) ?></strong></div>
                    <?php endif; ?>
                    <div class="text-body-secondary mt-1"><i class="fa-solid fa-camera me-1"></i>Se guarda una copia de estos datos al momento del servicio.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Datos del servicio -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-body-secondary mb-3">Datos del servicio</h2>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label d-block">Tipo <span class="text-danger">*</span></label>
                    <?php foreach ($tipos as $t): ?>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="tipo" id="tipo-<?= e($t) ?>" value="<?= e($t) ?>" <?= $tipoSel === $t ? 'checked' : '' ?>>
                            <label class="form-check-label" for="tipo-<?= e($t) ?>"><?= e(etiqueta($t)) ?></label>
                        </div>
                    <?php endforeach; ?>
                    <?php if (error('tipo') !== null): ?><div class="text-danger small"><?= e(error('tipo')) ?></div><?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="tecnico_id" class="form-label">Técnico</label>
                    <?php if ($tecnicos !== []): ?>
                        <select id="tecnico_id" name="tecnico_id" class="form-select <?= error('tecnico_id') !== null ? 'is-invalid' : '' ?>">
                            <?php foreach ($tecnicos as $t): ?>
                                <option value="<?= e($t['id']) ?>" <?= $tecnicoSel === (string) $t['id'] ? 'selected' : '' ?>><?= e($t['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="invalid-feedback"><?= e(error('tecnico_id')) ?></div>
                    <?php else: ?>
                        <input type="text" class="form-control" disabled
                               value="<?= e($esNueva ? auth_user()['nombres'] . ' ' . auth_user()['apellidos'] : $acta['tecnico_nombres'] . ' ' . $acta['tecnico_apellidos']) ?>">
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="fecha_ingreso" class="form-label">Ingreso (fecha y hora) <span class="text-danger">*</span></label>
                    <input type="datetime-local" id="fecha_ingreso" name="fecha_ingreso" class="form-control <?= error('fecha_ingreso') !== null ? 'is-invalid' : '' ?>" value="<?= e($ingreso) ?>">
                    <div class="invalid-feedback"><?= e(error('fecha_ingreso')) ?></div>
                </div>
                <div class="col-md-3">
                    <label for="fecha_salida" class="form-label">Salida (fecha y hora)</label>
                    <input type="datetime-local" id="fecha_salida" name="fecha_salida" class="form-control <?= error('fecha_salida') !== null ? 'is-invalid' : '' ?>" value="<?= e($salida) ?>">
                    <div class="invalid-feedback"><?= e(error('fecha_salida')) ?></div>
                    <div class="form-text">Obligatoria para cerrar el acta.</div>
                </div>
                <div class="col-12">
                    <label for="problema_reportado" class="form-label">Problema reportado / motivo del servicio</label>
                    <textarea id="problema_reportado" name="problema_reportado" class="form-control <?= error('problema_reportado') !== null ? 'is-invalid' : '' ?>" rows="2" maxlength="2000"><?= e($v('problema_reportado', (string) ($acta['problema_reportado'] ?? ''))) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('problema_reportado')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Checklists -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-body-secondary mb-3">Actividades realizadas</h2>
            <div class="row g-3">
                <?php foreach ($checklist as $categoria => $items): ?>
                    <div class="col-md-4">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-2"><i class="fa-solid <?= e($nombresCat[$categoria][1]) ?> me-1 text-primary"></i><?= e($nombresCat[$categoria][0]) ?></div>
                            <?php if ($items === []): ?>
                                <p class="small text-body-secondary mb-0">No hay actividades de esta categoría para <?= e(mb_strtolower(etiqueta($equipo['tipo']))) ?>.</p>
                            <?php endif; ?>
                            <?php foreach ($items as $item): ?>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="checklist[]" value="<?= e($item['id']) ?>" id="chk-<?= e($item['id']) ?>"
                                           <?= in_array((int) $item['id'], $marcados, true) ? 'checked' : '' ?>
                                           <?= $item['codigo'] === 'FORMATEO_SO' ? 'data-formateo="1"' : '' ?>>
                                    <label class="form-check-label small" for="chk-<?= e($item['id']) ?>"><?= e($item['descripcion']) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($licencia !== null): ?>
                <div class="alert alert-warning mt-3 mb-0 <?= error('licencia_accion') !== null ? '' : 'd-none' ?>" id="pregunta-licencia">
                    <div class="fw-semibold mb-1"><i class="fa-brands fa-microsoft me-1"></i>Licencia Microsoft 365 del equipo</div>
                    <p class="small mb-2">
                        El equipo ocupa el slot <?= e($licencia['slot']) ?> de la cuenta <?= e($licencia['correo']) ?> (<?= e($licencia['codigo']) ?>).
                        Al formatear, ¿qué desea hacer con esa instalación?
                    </p>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="licencia_accion" id="lic-mantener" value="MANTENER" <?= $licenciaAccion === 'MANTENER' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="lic-mantener">Mantener (se reinstalará Office con la misma cuenta)</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="licencia_accion" id="lic-liberar" value="LIBERAR" <?= $licenciaAccion === 'LIBERAR' ? 'checked' : '' ?>>
                        <label class="form-check-label small" for="lic-liberar">Liberar la instalación (motivo: formateo)</label>
                    </div>
                    <?php if (error('licencia_accion') !== null): ?><div class="text-danger small mt-1"><?= e(error('licencia_accion')) ?></div><?php endif; ?>
                    <div class="small text-body-secondary mt-1">La liberación se aplica al guardar y queda en el historial de la cuenta.</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Componentes -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-body-secondary mb-3">Componentes</h2>
            <?php if (error('componentes') !== null): ?><div class="alert alert-danger py-2 small"><?= e(error('componentes')) ?></div><?php endif; ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr><th>Componente</th><?php foreach ($acciones as $a): ?><th class="text-center"><?= e(etiqueta($a)) ?></th><?php endforeach; ?><th>Detalle (serie o capacidad nueva)</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($listaComponentes as $c):
                        $sel = (string) ($compAccion[$c] ?? 'NO_APLICA');
                        ?>
                        <tr>
                            <td class="fw-semibold"><?= e($c) ?></td>
                            <?php foreach ($acciones as $a): ?>
                                <td class="text-center">
                                    <input class="form-check-input" type="radio" name="comp_accion[<?= e($c) ?>]" value="<?= e($a) ?>"
                                           aria-label="<?= e($c . ' ' . etiqueta($a)) ?>" <?= $sel === $a ? 'checked' : '' ?>>
                                </td>
                            <?php endforeach; ?>
                            <td><input type="text" name="comp_detalle[<?= e($c) ?>]" class="form-control form-control-sm" maxlength="150" value="<?= e((string) ($compDetalle[$c] ?? '')) ?>"></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Software -->
    <?php if (!$esImpresora): ?>
        <div class="card shadow-sm border-0 mb-3">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Software instalado</h2>
                <?php if (error('software') !== null): ?><div class="alert alert-danger py-2 small"><?= e(error('software')) ?></div><?php endif; ?>
                <div class="row g-2">
                    <?php foreach ($catalogo as $s):
                        $sid = (int) $s['id'];
                        $marcado = in_array($sid, $softMarcado, true);
                        ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="input-group input-group-sm">
                                <span class="input-group-text flex-grow-1 bg-body">
                                    <input class="form-check-input mt-0 me-2" type="checkbox" name="software[]" value="<?= e($sid) ?>" id="sw-<?= e($sid) ?>" <?= $marcado ? 'checked' : '' ?>>
                                    <label for="sw-<?= e($sid) ?>" class="mb-0"><?= e($s['nombre']) ?><?= (int) $s['activo'] === 1 ? '' : ' (inactivo)' ?></label>
                                </span>
                                <input type="text" name="software_version[<?= e($sid) ?>]" class="form-control" style="max-width: 110px;" maxlength="40"
                                       placeholder="Versión" aria-label="Versión de <?= e($s['nombre']) ?>" value="<?= e((string) ($softVersion[$sid] ?? '')) ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Observaciones -->
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="form-control <?= error('observaciones') !== null ? 'is-invalid' : '' ?>" rows="3" maxlength="2000"><?= e($v('observaciones', (string) ($acta['observaciones'] ?? ''))) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('observaciones')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="recomendaciones" class="form-label">Recomendaciones</label>
                    <textarea id="recomendaciones" name="recomendaciones" class="form-control <?= error('recomendaciones') !== null ? 'is-invalid' : '' ?>" rows="3" maxlength="2000"><?= e($v('recomendaciones', (string) ($acta['recomendaciones'] ?? ''))) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('recomendaciones')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Cierre -->
    <div class="card shadow-sm border-0 mb-3 border-start border-4 border-success">
        <div class="card-body">
            <h2 class="h6 text-uppercase text-body-secondary mb-1">Cierre del acta</h2>
            <p class="small text-body-secondary mb-3">Solo al cerrar: el acta recibe su número correlativo, se vuelve inmutable y se genera el PDF.</p>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="estado_equipo_final" class="form-label">Estado en que queda el equipo</label>
                    <select id="estado_equipo_final" name="estado_equipo_final" class="form-select <?= error('estado_equipo_final') !== null ? 'is-invalid' : '' ?>">
                        <?php foreach ($estadosFinales as $s): ?>
                            <option value="<?= e($s) ?>" <?= $v('estado_equipo_final', 'OPERATIVO') === $s ? 'selected' : '' ?>><?= e(etiqueta($s)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('estado_equipo_final')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="condicion_final" class="form-label">Condición física</label>
                    <select id="condicion_final" name="condicion_final" class="form-select <?= error('condicion_final') !== null ? 'is-invalid' : '' ?>">
                        <?php foreach ($condiciones as $c): ?>
                            <option value="<?= e($c) ?>" <?= $v('condicion_final', (string) $equipo['condicion_fisica']) === $c ? 'selected' : '' ?>><?= e(etiqueta($c)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('condicion_final')) ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex flex-wrap justify-content-end gap-2 mb-4">
        <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar borrador</button>
        <button type="button" class="btn btn-success" id="btn-cerrar" data-ahora="<?= e(date('Y-m-d\TH:i')) ?>"><i class="fa-solid fa-lock me-1"></i>Guardar y cerrar acta</button>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-acta');
    var formateo = form.querySelector('[data-formateo]');
    var pregunta = document.getElementById('pregunta-licencia');

    function revisarFormateo() {
        if (pregunta && formateo) {
            pregunta.classList.toggle('d-none', !formateo.checked);
        }
    }
    if (formateo) {
        formateo.addEventListener('change', revisarFormateo);
    }
    revisarFormateo();

    // Detalle de componentes: solo editable si la acción no es "No aplica".
    function revisarComponente(nombre) {
        var sel = form.querySelector('input[name="comp_accion[' + nombre + ']"]:checked');
        var detalle = form.querySelector('input[name="comp_detalle[' + nombre + ']"]');
        var aplica = sel && sel.value !== 'NO_APLICA';
        detalle.readOnly = !aplica;
        detalle.placeholder = aplica ? 'Obligatorio' : '';
    }
    form.querySelectorAll('input[name^="comp_accion["]').forEach(function (radio) {
        var nombre = radio.name.slice('comp_accion['.length, -1);
        radio.addEventListener('change', function () { revisarComponente(nombre); });
        revisarComponente(nombre);
    });

    document.getElementById('btn-cerrar').addEventListener('click', function () {
        // Hora del servidor (America/Lima), no la del navegador.
        if (document.getElementById('fecha_salida').value === '') {
            document.getElementById('fecha_salida').value = this.dataset.ahora;
        }
        if (!window.confirm('¿Cerrar el acta? Recibirá su número correlativo y ya no podrá modificarse.')) {
            return;
        }
        document.getElementById('accion').value = 'cerrar';
        form.requestSubmit();
    });
});
</script>
