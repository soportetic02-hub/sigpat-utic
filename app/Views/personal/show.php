<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $persona
 * @var list<array<string, mixed>> $equipos
 * @var list<array<string, mixed>> $historial
 */
$activo = (int) $persona['activo'] === 1;
$nombre = $persona['nombres'] . ' ' . $persona['apellidos'];
$estados = [
    'OPERATIVO'        => 'text-bg-success',
    'EN_MANTENIMIENTO' => 'text-bg-warning',
    'INOPERATIVO'      => 'text-bg-danger',
    'DE_BAJA'          => 'text-bg-dark',
];
$motivos = [
    'ALTA'           => 'text-bg-info',
    'ASIGNACION'     => 'text-bg-primary',
    'DESVINCULACION' => 'text-bg-secondary',
    'TRASLADO'       => 'text-bg-warning',
    'ROTACION'       => 'text-bg-warning',
    'BAJA'           => 'text-bg-dark',
];
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i><?= e($nombre) ?>
        <?php if (!$activo): ?><span class="badge text-bg-secondary align-middle ms-2 fs-6">Inactivo</span><?php endif; ?>
    </h1>
    <div class="d-flex gap-2">
        <a href="<?= e(url('personal')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <a href="<?= e(url('personal/' . $persona['id'] . '/editar')) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Editar</a>
        <?php if (has_role('ADMINISTRADOR')): ?>
            <form method="post" action="<?= e(url('personal/' . $persona['id'] . '/activo')) ?>"
                  data-confirm="<?= e($activo ? '¿Desactivar a ' . $nombre . '?' : '¿Activar a ' . $nombre . '?') ?>">
                <?= csrf_field() ?>
                <?php if ($activo): ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-user-slash me-1"></i>Desactivar</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-outline-success"><i class="fa-solid fa-user-check me-1"></i>Activar</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Datos personales</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">DNI</dt><dd class="col-7"><?= e($persona['dni'] ?? '—') ?></dd>
                    <dt class="col-5">Cargo</dt><dd class="col-7"><?= e($persona['cargo'] ?? '—') ?></dd>
                    <dt class="col-5">Oficina</dt><dd class="col-7"><?= e($persona['oficina_nombre']) ?></dd>
                    <dt class="col-5">Correo</dt><dd class="col-7 text-break"><?= e($persona['email'] ?? '—') ?></dd>
                    <dt class="col-5">Teléfono</dt><dd class="col-7"><?= e($persona['telefono'] ?? '—') ?></dd>
                    <dt class="col-5">Registrado</dt><dd class="col-7"><?= e(fecha((string) $persona['created_at'])) ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h2 class="h6 text-uppercase text-body-secondary mb-0">Equipos a su cargo (<?= e(count($equipos)) ?>)</h2>
                    <?php if ($activo): ?>
                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modal-asignar">
                            <i class="fa-solid fa-plus me-1"></i>Asignar equipo
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($equipos === []): ?>
                    <p class="text-body-secondary small mb-0">No tiene equipos asignados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Equipo</th>
                                    <th class="d-none d-md-table-cell">Serie / Patrimonial</th>
                                    <th class="d-none d-md-table-cell">Red</th>
                                    <th>Ubicación</th>
                                    <th>Estado</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($equipos as $eq): ?>
                                <tr>
                                    <td><a href="<?= e(url('equipos/' . $eq['id'])) ?>" class="text-decoration-none"><span class="fw-semibold"><?= e($eq['tipo']) ?></span> <?= e($eq['marca'] . ' ' . $eq['modelo']) ?></a></td>
                                    <td class="d-none d-md-table-cell small"><?= e($eq['nro_serie'] ?? '—') ?><br><span class="text-body-secondary"><?= e($eq['codigo_patrimonial'] ?? '—') ?></span></td>
                                    <td class="d-none d-md-table-cell small"><?= e($eq['hostname'] ?? '—') ?><br><span class="text-body-secondary"><?= e($eq['ip_lan'] ?? '') ?></span></td>
                                    <td class="small"><?= e($eq['oficina_siglas'] ?? $eq['oficina_nombre']) ?></td>
                                    <td><span class="badge <?= e($estados[$eq['estado_operativo']] ?? 'text-bg-light') ?>"><?= e(str_replace('_', ' ', (string) $eq['estado_operativo'])) ?></span></td>
                                    <td class="text-end">
                                        <form method="post" action="<?= e(url('personal/' . $persona['id'] . '/equipos/' . $eq['id'] . '/desvincular')) ?>"
                                              data-confirm="¿Desvincular este equipo? Quedará en su oficina actual sin responsable.">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Desvincular"><i class="fa-solid fa-link-slash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Historial de asignaciones</h2>
                <?php if ($historial === []): ?>
                    <p class="text-body-secondary small mb-0">Sin movimientos registrados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Equipo</th>
                                    <th>Oficina</th>
                                    <th>Desde</th>
                                    <th>Hasta</th>
                                    <th>Motivo</th>
                                    <th class="d-none d-lg-table-cell">Observación</th>
                                    <th class="d-none d-md-table-cell">Registró</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr>
                                    <td class="small">
                                        <?= e($h['tipo'] . ' ' . $h['marca'] . ' ' . $h['modelo']) ?>
                                        <div class="text-body-secondary"><?= e($h['nro_serie'] ?? $h['codigo_patrimonial'] ?? '') ?></div>
                                    </td>
                                    <td class="small"><?= e($h['oficina_nombre']) ?></td>
                                    <td class="small text-nowrap"><?= e(fecha((string) $h['fecha_inicio'], true)) ?></td>
                                    <td class="small text-nowrap">
                                        <?php if ($h['fecha_fin'] === null): ?>
                                            <span class="badge text-bg-success">Vigente</span>
                                        <?php else: ?>
                                            <?= e(fecha((string) $h['fecha_fin'], true)) ?>
                                            <?php if ($h['motivo_cierre'] !== null): ?>
                                                <div><span class="badge <?= e($motivos[$h['motivo_cierre']] ?? 'text-bg-light') ?>" title="Motivo del cierre"><?= e($h['motivo_cierre']) ?></span></div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge <?= e($motivos[$h['motivo']] ?? 'text-bg-light') ?>"><?= e($h['motivo']) ?></span></td>
                                    <td class="small d-none d-lg-table-cell"><?= e($h['observacion'] ?? '') ?></td>
                                    <td class="small d-none d-md-table-cell"><?= e($h['registrado_por'] ?? '—') ?></td>
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

<?php if ($activo): ?>
<div class="modal fade" id="modal-asignar" tabindex="-1" aria-labelledby="modal-asignar-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form method="post" action="<?= e(url('personal/' . $persona['id'] . '/equipos')) ?>" class="modal-content" id="form-asignar">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-asignar-titulo"><i class="fa-solid fa-link me-2"></i>Asignar equipo a <?= e($nombre) ?></h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <label for="buscar-equipo" class="form-label">Buscar equipo</label>
                <input type="search" id="buscar-equipo" class="form-control mb-3" autocomplete="off"
                       placeholder="Serie, código patrimonial, hostname, IP, marca o modelo (mín. 2 caracteres)"
                       data-url="<?= e(url('personal/' . $persona['id'] . '/equipos-disponibles')) ?>">
                <div id="resultados-equipos" class="list-group mb-3">
                    <div class="list-group-item text-body-secondary small">Escriba para buscar equipos disponibles.</div>
                </div>

                <div class="row g-2">
                    <div class="col-md-6">
                        <label class="form-label small mb-1">Ubicación del equipo</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ubicacion" id="ubic-persona" value="persona" checked>
                            <label class="form-check-label small" for="ubic-persona">Trasladar a la oficina de la persona (<?= e($persona['oficina_siglas'] ?? $persona['oficina_nombre']) ?>)</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ubicacion" id="ubic-actual" value="actual">
                            <label class="form-check-label small" for="ubic-actual">Mantener su ubicación actual</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <label for="observacion" class="form-label small mb-1">Observación</label>
                        <input type="text" id="observacion" name="observacion" class="form-control form-control-sm" maxlength="255">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-primary" id="btn-asignar" disabled><i class="fa-solid fa-link me-1"></i>Asignar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('buscar-equipo');
    var contenedor = document.getElementById('resultados-equipos');
    var boton = document.getElementById('btn-asignar');
    var temporizador = null;

    function mensaje(texto) {
        contenedor.innerHTML = '';
        var item = document.createElement('div');
        item.className = 'list-group-item text-body-secondary small';
        item.textContent = texto;
        contenedor.appendChild(item);
        boton.disabled = true;
    }

    function pintar(equipos) {
        if (equipos.length === 0) {
            mensaje('No se encontraron equipos disponibles.');
            return;
        }
        contenedor.innerHTML = '';
        equipos.forEach(function (eq) {
            var label = document.createElement('label');
            label.className = 'list-group-item list-group-item-action d-flex gap-2 align-items-start';

            var radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'equipo_id';
            radio.value = eq.id;
            radio.className = 'form-check-input mt-1';
            radio.addEventListener('change', function () { boton.disabled = false; });

            var cuerpo = document.createElement('div');
            var titulo = document.createElement('div');
            titulo.className = 'fw-semibold';
            titulo.textContent = eq.tipo + ' ' + eq.marca + ' ' + eq.modelo;
            var detalle = document.createElement('div');
            detalle.className = 'small text-body-secondary';
            detalle.textContent = 'Serie: ' + (eq.nro_serie || '—') + ' · Patrimonial: ' + (eq.codigo_patrimonial || '—') +
                ' · ' + (eq.hostname || '') + ' ' + (eq.ip_lan || '') + ' · Ubicación: ' + eq.oficina_nombre;
            cuerpo.appendChild(titulo);
            cuerpo.appendChild(detalle);
            if (eq.personal_nombre) {
                var aviso = document.createElement('div');
                aviso.className = 'small text-warning-emphasis';
                aviso.textContent = 'Actualmente a cargo de ' + eq.personal_nombre + ' (se reasignará).';
                cuerpo.appendChild(aviso);
            }
            label.appendChild(radio);
            label.appendChild(cuerpo);
            contenedor.appendChild(label);
        });
        boton.disabled = true;
    }

    input.addEventListener('input', function () {
        clearTimeout(temporizador);
        var q = input.value.trim();
        if (q.length < 2) {
            mensaje('Escriba para buscar equipos disponibles.');
            return;
        }
        temporizador = setTimeout(function () {
            SIGPAT.fetchJson(input.dataset.url + '?q=' + encodeURIComponent(q))
                .then(function (datos) { pintar(datos.equipos); })
                .catch(function (error) { mensaje(error.message); });
        }, 300);
    });
});
</script>
<?php endif; ?>
