<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $licencia
 * @var array<int, array<string, mixed>|null> $slots  1..capacidad
 * @var int $ocupados
 * @var int $capacidad
 * @var list<array<string, mixed>> $liberados
 * @var list<string> $motivos
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 */
$id = (int) $licencia['id'];
$activa = (int) $licencia['activo'] === 1;
$admiteInstalaciones = $activa && $licencia['estado'] === 'ACTIVA';
$esAdmin = has_role('ADMINISTRADOR');
$porcentaje = (int) round($ocupados * 100 / $capacidad);
$colorBarra = $ocupados >= $capacidad ? 'bg-danger' : ($ocupados >= $capacidad - 1 ? 'bg-warning' : 'bg-primary');
$claseEstado = ['ACTIVA' => 'text-bg-success', 'SUSPENDIDA' => 'text-bg-warning', 'VENCIDA' => 'text-bg-danger', 'BAJA' => 'text-bg-dark'];
$motivosClase = ['FORMATEO' => 'text-bg-info', 'BAJA' => 'text-bg-dark', 'REASIGNACION' => 'text-bg-warning', 'OTRO' => 'text-bg-secondary'];

/** Nombre visible del equipo de un slot. */
$nombreEquipo = static function (array $s): string {
    if ($s['equipo_id'] === null) {
        return (string) $s['equipo_texto'];
    }

    return (string) ($s['hostname'] ?? trim($s['marca'] . ' ' . $s['modelo']));
};
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1"><i class="fa-brands fa-microsoft me-2 text-primary"></i><?= e($licencia['codigo']) ?></h1>
        <span class="text-body-secondary text-break"><?= e($licencia['correo']) ?></span>
        <span class="badge text-bg-light border ms-1"><?= e($licencia['plan']) ?></span>
        <span class="badge <?= e($claseEstado[$licencia['estado']] ?? 'text-bg-light border') ?> ms-1"><?= e(etiqueta($licencia['estado'])) ?></span>
        <?php if (!$activa): ?><span class="badge text-bg-secondary ms-1">Desactivada</span><?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(url('licencias')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <?php if ($esAdmin): ?>
            <a href="<?= e(url('licencias/' . $id . '/editar')) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Editar</a>
            <form method="post" action="<?= e(url('licencias/' . $id . '/activo')) ?>"
                  data-confirm="<?= e($activa ? '¿Desactivar esta cuenta? Solo es posible si no tiene instalaciones vigentes.' : '¿Activar esta cuenta?') ?>">
                <?= csrf_field() ?>
                <?php if ($activa): ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-ban me-1"></i>Desactivar</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-outline-success"><i class="fa-solid fa-rotate-left me-1"></i>Activar</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-4 fw-bold font-monospace"><?= e($ocupados) ?>/<?= e($capacidad) ?></span>
            <div class="flex-grow-1">
                <div class="progress" style="height: 14px;" role="progressbar" aria-label="Instalaciones usadas" aria-valuenow="<?= e($porcentaje) ?>" aria-valuemin="0" aria-valuemax="100">
                    <div class="progress-bar <?= e($colorBarra) ?>" style="width: <?= e($porcentaje) ?>%"></div>
                </div>
            </div>
            <span class="small text-body-secondary text-nowrap"><?= e($capacidad - $ocupados) ?> libre(s)</span>
        </div>
        <?php if (!$admiteInstalaciones): ?>
            <p class="small text-body-secondary mt-2 mb-0"><i class="fa-solid fa-circle-info me-1"></i>Solo una cuenta activa admite nuevas instalaciones.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <h2 class="h6 text-uppercase text-body-secondary mb-3">Instalaciones</h2>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="text-center">Slot</th>
                        <th>Equipo</th>
                        <th>Persona</th>
                        <th class="d-none d-md-table-cell">Oficina</th>
                        <th class="d-none d-lg-table-cell">Desde</th>
                        <th>Verificación</th>
                        <th class="text-end">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($slots as $numero => $s): ?>
                    <?php if ($s === null): ?>
                        <tr>
                            <td class="text-center fw-semibold"><?= e($numero) ?></td>
                            <td colspan="5"><span class="badge text-bg-success">Libre</span></td>
                            <td class="text-end">
                                <?php if ($admiteInstalaciones): ?>
                                    <button type="button" class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#modal-instalacion"
                                            data-modo="asignar" data-slot="<?= e($numero) ?>">
                                        <i class="fa-solid fa-plus me-1"></i>Asignar
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <td class="text-center fw-semibold"><?= e($numero) ?></td>
                            <td class="small">
                                <?php if ($s['equipo_id'] !== null): ?>
                                    <a href="<?= e(url('equipos/' . $s['equipo_id'])) ?>" class="fw-semibold text-decoration-none"><?= e($nombreEquipo($s)) ?></a>
                                    <div class="text-body-secondary"><?= e(trim($s['tipo'] . ' ' . $s['marca'] . ' ' . $s['modelo'])) ?><?= $s['nro_serie'] !== null ? ' · ' . e($s['nro_serie']) : '' ?></div>
                                <?php else: ?>
                                    <span class="fw-semibold"><?= e($s['equipo_texto']) ?></span>
                                    <div><span class="badge text-bg-light border">No inventariado</span></div>
                                <?php endif; ?>
                                <?php if ($s['observacion'] !== null): ?><div class="text-body-secondary fst-italic"><?= e($s['observacion']) ?></div><?php endif; ?>
                            </td>
                            <td class="small">
                                <?php if ($s['persona_nombre'] === null): ?>
                                    <span class="text-body-secondary">—</span>
                                <?php elseif ($s['personal_id'] !== null): ?>
                                    <a href="<?= e(url('personal/' . $s['personal_id'])) ?>" class="text-decoration-none"><?= e($s['persona_nombre']) ?></a>
                                <?php else: ?>
                                    <?= e($s['persona_nombre']) ?> <span class="badge text-bg-light border" title="No registrada en Personal">Texto</span>
                                <?php endif; ?>
                            </td>
                            <td class="small d-none d-md-table-cell"><?= e($s['oficina_siglas'] ?? $s['oficina_nombre'] ?? '—') ?></td>
                            <td class="small d-none d-lg-table-cell text-nowrap"><?= e(fecha((string) $s['fecha_asignacion'])) ?></td>
                            <td>
                                <?php if ($s['estado_verificacion'] === 'POR_VERIFICAR'): ?>
                                    <span class="badge text-bg-warning"><i class="fa-solid fa-circle-question me-1"></i>Por verificar</span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">OK</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#modal-liberar"
                                        data-slot-id="<?= e($s['id']) ?>" data-slot="<?= e($numero) ?>"
                                        data-equipo="<?= e($nombreEquipo($s) . ($s['persona_nombre'] !== null ? ' · ' . $s['persona_nombre'] : '')) ?>">
                                    <i class="fa-solid fa-unlock me-1"></i>Liberar
                                </button>
                                <?php if ($admiteInstalaciones): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" title="Reasignar a otro equipo" aria-label="Reasignar slot <?= e($numero) ?>"
                                            data-bs-toggle="modal" data-bs-target="#modal-instalacion"
                                            data-modo="reasignar" data-slot="<?= e($numero) ?>" data-slot-id="<?= e($s['id']) ?>">
                                        <i class="fa-solid fa-right-left"></i>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Datos de la cuenta</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Fecha de alta</dt><dd class="col-7"><?= e(fecha($licencia['fecha_alta']) ?: '—') ?></dd>
                    <dt class="col-5">Vencimiento</dt><dd class="col-7"><?= e(fecha($licencia['fecha_vencimiento']) ?: '—') ?></dd>
                    <dt class="col-5">Registrada</dt><dd class="col-7"><?= e(fecha((string) $licencia['created_at'])) ?></dd>
                    <?php if ($esAdmin): ?>
                        <dt class="col-5">Contraseña</dt>
                        <dd class="col-7">
                            <?php if ($licencia['contrasena_cifrada'] === null): ?>
                                <span class="text-body-secondary">Sin registrar</span>
                            <?php else: ?>
                                <div id="caja-contrasena" data-url="<?= e(url('licencias/' . $id . '/contrasena')) ?>">
                                    <button type="button" class="btn btn-outline-warning btn-sm" id="btn-ver-contrasena">
                                        <i class="fa-solid fa-eye me-1"></i>Mostrar
                                    </button>
                                    <div class="input-group input-group-sm d-none" id="grupo-contrasena">
                                        <input type="text" class="form-control font-monospace" id="valor-contrasena" readonly aria-label="Contraseña de la cuenta">
                                        <button type="button" class="btn btn-outline-secondary" id="btn-copiar-contrasena" title="Copiar" aria-label="Copiar la contraseña"><i class="fa-regular fa-copy"></i></button>
                                        <button type="button" class="btn btn-outline-secondary" id="btn-ocultar-contrasena" title="Ocultar" aria-label="Ocultar la contraseña"><i class="fa-solid fa-eye-slash"></i></button>
                                    </div>
                                    <div class="text-danger small mt-1 d-none" id="error-contrasena"></div>
                                </div>
                            <?php endif; ?>
                        </dd>
                    <?php endif; ?>
                </dl>
                <?php if ($licencia['observaciones'] !== null): ?>
                    <p class="small mt-3 mb-0" style="white-space: pre-line;"><?= e($licencia['observaciones']) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Historial de instalaciones liberadas</h2>
                <?php if ($liberados === []): ?>
                    <p class="small text-body-secondary mb-0">Ninguna instalación ha sido liberada todavía.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Slot</th><th>Equipo</th><th>Periodo</th><th>Motivo</th><th class="d-none d-md-table-cell">Liberó</th></tr></thead>
                            <tbody>
                            <?php foreach ($liberados as $h): ?>
                                <tr>
                                    <td class="text-center"><?= e($h['slot']) ?></td>
                                    <td class="small">
                                        <?php if ($h['equipo_id'] !== null): ?>
                                            <a href="<?= e(url('equipos/' . $h['equipo_id'])) ?>" class="text-decoration-none"><?= e($nombreEquipo($h)) ?></a>
                                        <?php else: ?>
                                            <?= e($h['equipo_texto']) ?>
                                        <?php endif; ?>
                                        <div class="text-body-secondary"><?= e($h['persona_nombre'] ?? '') ?></div>
                                    </td>
                                    <td class="small text-nowrap"><?= e(fecha((string) $h['fecha_asignacion'])) ?> → <?= e(fecha((string) $h['fecha_liberacion'], true)) ?></td>
                                    <td>
                                        <span class="badge <?= e($motivosClase[$h['motivo_liberacion']] ?? 'text-bg-light') ?>"><?= e(etiqueta($h['motivo_liberacion'])) ?></span>
                                        <?php if ($h['observacion'] !== null): ?><div class="small text-body-secondary"><?= e($h['observacion']) ?></div><?php endif; ?>
                                    </td>
                                    <td class="small d-none d-md-table-cell"><?= e($h['liberado_por_nombre'] ?? '—') ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Modal: asignar / reasignar instalación -->
<div class="modal fade" id="modal-instalacion" tabindex="-1" aria-labelledby="modal-instalacion-titulo" aria-hidden="true"
     data-url-equipos="<?= e(url('licencias/' . $id . '/equipos-elegibles')) ?>"
     data-url-personal="<?= e(url('personal/autocompletar')) ?>"
     data-url-asignar="<?= e(url('licencias/' . $id . '/slots')) ?>"
     data-url-base-slot="<?= e(url('licencias/' . $id . '/slots')) ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-instalacion-titulo">Asignar instalación</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-danger d-none small" id="instalacion-error" role="alert"></div>

                <label for="inst-equipo" class="form-label">Equipo <span class="text-danger">*</span></label>
                <input type="text" id="inst-equipo" class="form-control" maxlength="100" autocomplete="off"
                       placeholder="Busque en el inventario (hostname, serie, IP…) o escriba el nombre del dispositivo">
                <input type="hidden" id="inst-equipo-id">
                <div id="inst-equipo-lista" class="list-group mt-1"></div>
                <div class="form-text mb-3" id="inst-equipo-ayuda">Sin elegir de la lista se guarda como texto libre (tablet, equipo no inventariado).</div>

                <label for="inst-persona" class="form-label">Persona</label>
                <input type="text" id="inst-persona" class="form-control" maxlength="150" autocomplete="off"
                       placeholder="Busque en Personal o escriba el nombre">
                <input type="hidden" id="inst-personal-id">
                <div id="inst-persona-lista" class="list-group mt-1"></div>
                <div class="form-text mb-3" id="inst-persona-ayuda">Sin elegir de la lista se guarda como texto libre.</div>

                <div class="row g-3">
                    <div class="col-md-8">
                        <label for="inst-oficina" class="form-label">Oficina</label>
                        <select id="inst-oficina" class="form-select">
                            <option value="">— La del equipo del inventario, o ninguna —</option>
                            <?php foreach ($oficinas as $op): ?>
                                <option value="<?= e($op['id']) ?>"><?= str_repeat('&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="inst-verificar">
                            <label class="form-check-label" for="inst-verificar">Por verificar</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <label for="inst-observacion" class="form-label small">Observación (opcional)</label>
                        <input type="text" id="inst-observacion" class="form-control form-control-sm" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btn-confirmar-instalacion" disabled><i class="fa-solid fa-check me-1"></i>Confirmar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: liberar -->
<div class="modal fade" id="modal-liberar" tabindex="-1" aria-labelledby="modal-liberar-titulo" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" class="modal-content" id="form-liberar" data-url-base="<?= e(url('licencias/' . $id . '/slots')) ?>">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-liberar-titulo"><i class="fa-solid fa-unlock me-2 text-danger"></i>Liberar slot <span id="liberar-numero"></span></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="small mb-3">Instalación: <strong id="liberar-equipo"></strong></p>
                <label for="motivo" class="form-label">Motivo <span class="text-danger">*</span></label>
                <select id="motivo" name="motivo" class="form-select mb-3" required>
                    <?php foreach ($motivos as $m): ?>
                        <?php if ($m !== 'REASIGNACION'): ?>
                            <option value="<?= e($m) ?>"><?= e(etiqueta($m)) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <label for="observacion-liberar" class="form-label">Observación</label>
                <input type="text" id="observacion-liberar" name="observacion" class="form-control" maxlength="255">
                <p class="small text-body-secondary mt-3 mb-0">La instalación no se borra: queda en el historial de la cuenta.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-unlock me-1"></i>Liberar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // ---------- Liberar ----------
    document.getElementById('modal-liberar').addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;
        var form = document.getElementById('form-liberar');
        form.action = form.dataset.urlBase + '/' + boton.dataset.slotId + '/liberar';
        document.getElementById('liberar-numero').textContent = boton.dataset.slot;
        document.getElementById('liberar-equipo').textContent = boton.dataset.equipo;
    });

    // ---------- Mostrar contraseña (solo administradores; queda en la auditoría) ----------
    var cajaContrasena = document.getElementById('caja-contrasena');
    if (cajaContrasena) {
        var botonVer = document.getElementById('btn-ver-contrasena');
        var grupo = document.getElementById('grupo-contrasena');
        var campo = document.getElementById('valor-contrasena');
        var errorContrasena = document.getElementById('error-contrasena');
        var temporizadorOcultar = null;
        var ocultar = function () {
            campo.value = '';
            grupo.classList.add('d-none');
            botonVer.classList.remove('d-none');
            clearTimeout(temporizadorOcultar);
        };
        botonVer.addEventListener('click', function () {
            if (!window.confirm('La contraseña se mostrará en pantalla y la consulta quedará registrada en la auditoría. ¿Continuar?')) {
                return;
            }
            errorContrasena.classList.add('d-none');
            var datos = new URLSearchParams();
            datos.append('_csrf', SIGPAT.csrfToken);
            SIGPAT.fetchJson(cajaContrasena.dataset.url, { method: 'POST', body: datos })
                .then(function (r) {
                    campo.value = r.contrasena;
                    botonVer.classList.add('d-none');
                    grupo.classList.remove('d-none');
                    // Se oculta sola al minuto.
                    temporizadorOcultar = setTimeout(ocultar, 60000);
                })
                .catch(function (error) {
                    errorContrasena.textContent = error.message;
                    errorContrasena.classList.remove('d-none');
                });
        });
        document.getElementById('btn-ocultar-contrasena').addEventListener('click', ocultar);
        document.getElementById('btn-copiar-contrasena').addEventListener('click', function () {
            campo.select();
            if (navigator.clipboard) {
                navigator.clipboard.writeText(campo.value);
            } else {
                document.execCommand('copy');
            }
        });
    }

    // ---------- Asignar / reasignar (AJAX con token CSRF) ----------
    var modal = document.getElementById('modal-instalacion');
    var botonConfirmar = document.getElementById('btn-confirmar-instalacion');
    var cajaError = document.getElementById('instalacion-error');
    var estado = { modo: 'asignar', slot: null, slotId: null };

    var inputEquipo = document.getElementById('inst-equipo');
    var equipoId = document.getElementById('inst-equipo-id');
    var listaEquipos = document.getElementById('inst-equipo-lista');
    var inputPersona = document.getElementById('inst-persona');
    var personalId = document.getElementById('inst-personal-id');
    var listaPersonas = document.getElementById('inst-persona-lista');
    var selectOficina = document.getElementById('inst-oficina');

    function actualizarBoton() {
        botonConfirmar.disabled = inputEquipo.value.trim() === '';
    }

    function limpiarLista(lista) {
        lista.innerHTML = '';
    }

    /**
     * Autocompletar: al escribir busca en el servidor; al elegir una opción guarda su id en
     * el campo oculto. Si se vuelve a escribir, el id se borra y el texto queda como texto libre.
     */
    function autocompletar(input, oculto, lista, url, clave, pintarItem, alElegir) {
        var temporizador = null;
        input.addEventListener('input', function () {
            oculto.value = '';
            input.classList.remove('is-valid');
            actualizarBoton();
            clearTimeout(temporizador);
            var q = input.value.trim();
            if (q.length < 2) {
                limpiarLista(lista);
                return;
            }
            temporizador = setTimeout(function () {
                SIGPAT.fetchJson(url + '?q=' + encodeURIComponent(q))
                    .then(function (datos) {
                        limpiarLista(lista);
                        (datos[clave] || []).forEach(function (item) {
                            var boton = document.createElement('button');
                            boton.type = 'button';
                            boton.className = 'list-group-item list-group-item-action small';
                            pintarItem(boton, item);
                            boton.addEventListener('click', function () {
                                alElegir(item);
                                input.classList.add('is-valid');
                                limpiarLista(lista);
                                actualizarBoton();
                            });
                            lista.appendChild(boton);
                        });
                    })
                    .catch(function () { limpiarLista(lista); });
            }, 300);
        });
    }

    function lineas(boton, titulo, detalle) {
        var t = document.createElement('div');
        t.className = 'fw-semibold';
        t.textContent = titulo;
        var d = document.createElement('div');
        d.className = 'text-body-secondary';
        d.textContent = detalle;
        boton.appendChild(t);
        boton.appendChild(d);
    }

    autocompletar(inputEquipo, equipoId, listaEquipos, modal.dataset.urlEquipos, 'equipos',
        function (boton, eq) {
            lineas(boton, (eq.hostname || (eq.marca + ' ' + eq.modelo)) + ' · ' + eq.tipo,
                'Serie: ' + (eq.nro_serie || '—') + ' · ' + (eq.personal_nombre || 'Sin responsable') + ' (' + eq.oficina_nombre + ')');
        },
        function (eq) {
            equipoId.value = eq.id;
            inputEquipo.value = eq.hostname || (eq.marca + ' ' + eq.modelo);
            // Precarga la persona y la oficina del inventario si aún no se eligieron.
            if (inputPersona.value.trim() === '' && eq.personal_id) {
                personalId.value = eq.personal_id;
                inputPersona.value = eq.personal_nombre;
                inputPersona.classList.add('is-valid');
            }
            if (selectOficina.value === '') {
                selectOficina.value = String(eq.oficina_id);
            }
        });

    autocompletar(inputPersona, personalId, listaPersonas, modal.dataset.urlPersonal, 'personal',
        function (boton, p) {
            lineas(boton, p.nombres + ' ' + p.apellidos, (p.cargo || 'Sin cargo') + ' · ' + p.oficina_nombre);
        },
        function (p) {
            personalId.value = p.id;
            inputPersona.value = p.nombres + ' ' + p.apellidos;
        });

    modal.addEventListener('show.bs.modal', function (evento) {
        var boton = evento.relatedTarget;
        estado.modo = boton.dataset.modo;
        estado.slot = boton.dataset.slot;
        estado.slotId = boton.dataset.slotId || null;
        document.getElementById('modal-instalacion-titulo').textContent =
            (estado.modo === 'reasignar' ? 'Reasignar slot ' : 'Asignar instalación al slot ') + estado.slot;
        [inputEquipo, equipoId, inputPersona, personalId, document.getElementById('inst-observacion')].forEach(function (c) { c.value = ''; });
        inputEquipo.classList.remove('is-valid');
        inputPersona.classList.remove('is-valid');
        selectOficina.value = '';
        document.getElementById('inst-verificar').checked = false;
        limpiarLista(listaEquipos);
        limpiarLista(listaPersonas);
        cajaError.classList.add('d-none');
        actualizarBoton();
    });
    modal.addEventListener('shown.bs.modal', function () { inputEquipo.focus(); });

    botonConfirmar.addEventListener('click', function () {
        var datos = new URLSearchParams();
        datos.append('_csrf', SIGPAT.csrfToken);
        if (equipoId.value !== '') {
            datos.append('equipo_id', equipoId.value);
        } else {
            datos.append('equipo_texto', inputEquipo.value.trim());
        }
        if (personalId.value !== '') {
            datos.append('personal_id', personalId.value);
        } else {
            datos.append('usuario_texto', inputPersona.value.trim());
        }
        datos.append('oficina_id', selectOficina.value);
        datos.append('estado_verificacion', document.getElementById('inst-verificar').checked ? 'POR_VERIFICAR' : 'OK');
        datos.append('observacion', document.getElementById('inst-observacion').value);
        var url;
        if (estado.modo === 'reasignar') {
            url = modal.dataset.urlBaseSlot + '/' + estado.slotId + '/reasignar';
        } else {
            url = modal.dataset.urlAsignar;
            datos.append('slot', estado.slot);
        }
        botonConfirmar.disabled = true;
        cajaError.classList.add('d-none');
        SIGPAT.fetchJson(url, { method: 'POST', body: datos })
            .then(function () { window.location.reload(); })
            .catch(function (error) {
                cajaError.textContent = error.message;
                cajaError.classList.remove('d-none');
                botonConfirmar.disabled = false;
            });
    });
});
</script>
