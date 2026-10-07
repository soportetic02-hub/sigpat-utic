<?php

declare(strict_types=1);

/**
 * @var string $pestana  parametros | software | checklist
 * @var list<array<string, mixed>> $parametros
 * @var list<array<string, mixed>> $software
 * @var list<array<string, mixed>> $checklist
 * @var list<string> $categorias
 * @var list<string> $tiposEquipo
 */
$pestanas = [
    'parametros' => ['Parámetros', 'fa-sliders'],
    'software'   => ['Catálogo de software', 'fa-compact-disc'],
    'checklist'  => ['Checklist de mantenimiento', 'fa-list-check'],
];
$nombresCat = ['FISICO' => 'Físico', 'LOGICO' => 'Lógico', 'RED' => 'Red'];
?>
<h1 class="h4 mb-3"><i class="fa-solid fa-sliders me-2 text-primary"></i>Parámetros y catálogos</h1>

<ul class="nav nav-tabs">
    <?php foreach ($pestanas as $clave => [$titulo, $icono]): ?>
        <li class="nav-item">
            <a class="nav-link <?= $pestana === $clave ? 'active' : '' ?>" href="<?= e(url('parametros', ['pestana' => $clave])) ?>">
                <i class="fa-solid <?= e($icono) ?> me-1"></i><?= e($titulo) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<div class="card shadow-sm border-0 border-top-0 rounded-top-0">
    <div class="card-body">
    <?php if ($pestana === 'parametros'): ?>
        <form method="post" action="<?= e(url('parametros')) ?>" novalidate>
            <?= csrf_field() ?>
            <?php foreach ($parametros as $p):
                $clave = (string) $p['clave'];
                $campo = 'param_' . $clave;
                $valor = old_array('param')[$clave] ?? $p['valor'];
                $editable = (int) $p['editable'] === 1;
                ?>
                <div class="row g-2 align-items-start mb-3">
                    <div class="col-md-4">
                        <label for="<?= e($campo) ?>" class="form-label mb-0 fw-semibold small"><?= e($p['descripcion'] ?? $clave) ?></label>
                        <div class="small text-body-secondary font-monospace"><?= e($clave) ?> · <?= e(etiqueta((string) $p['tipo'])) ?></div>
                    </div>
                    <div class="col-md-5">
                        <?php if ($p['tipo'] === 'BOOLEANO'): ?>
                            <select id="<?= e($campo) ?>" name="param[<?= e($clave) ?>]" class="form-select <?= error($campo) !== null ? 'is-invalid' : '' ?>" <?= $editable ? '' : 'disabled' ?>>
                                <option value="1" <?= (string) $valor === '1' ? 'selected' : '' ?>>Sí</option>
                                <option value="0" <?= (string) $valor === '0' ? 'selected' : '' ?>>No</option>
                            </select>
                        <?php else: ?>
                            <input type="<?= $p['tipo'] === 'ENTERO' ? 'number' : ($p['tipo'] === 'FECHA' ? 'date' : ($clave === 'jefe_otic_email' ? 'email' : 'text')) ?>"
                                   id="<?= e($campo) ?>" name="param[<?= e($clave) ?>]" maxlength="500"
                                   class="form-control <?= error($campo) !== null ? 'is-invalid' : '' ?>" value="<?= e((string) $valor) ?>" <?= $editable ? '' : 'disabled' ?>>
                        <?php endif; ?>
                        <div class="invalid-feedback"><?= e(error($campo)) ?></div>
                    </div>
                    <div class="col-md-3 small text-body-secondary">
                        <?php if (!$editable): ?><span class="badge text-bg-secondary">Solo lectura</span><?php endif; ?>
                        <?php if ($p['actualizado_por'] !== null): ?>Modificado por <?= e($p['actualizado_por']) ?><br><?= e(fecha((string) $p['updated_at'], true)) ?><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="alert alert-info small">
                <i class="fa-solid fa-circle-info me-1"></i>Los datos del jefe de la OTIC son el destinatario por defecto de los informes técnicos; la periodicidad de mantenimiento
                se aplica a los equipos que no definen la suya y recalcula al instante las alertas del dashboard.
            </div>
            <div class="text-end"><button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar parámetros</button></div>
        </form>

    <?php elseif ($pestana === 'software'): ?>
        <p class="small text-body-secondary">Software que el técnico puede marcar como instalado en las actas. Desactivar un elemento lo oculta en las actas nuevas sin alterar las existentes.</p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th>Nombre</th><th>Descripción</th><th style="width: 90px;">Orden</th><th class="text-center">Usos</th><th>Estado</th><th class="text-end" style="width: 150px;"></th></tr></thead>
                <tbody>
                <?php foreach ($software as $s): $f = 'f-sw-' . $s['id']; ?>
                    <tr class="<?= (int) $s['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                        <td><input form="<?= e($f) ?>" name="nombre" class="form-control form-control-sm" maxlength="100" value="<?= e($s['nombre']) ?>" required aria-label="Nombre"></td>
                        <td><input form="<?= e($f) ?>" name="descripcion" class="form-control form-control-sm" maxlength="255" value="<?= e($s['descripcion'] ?? '') ?>" aria-label="Descripción"></td>
                        <td><input form="<?= e($f) ?>" name="orden" type="number" min="0" max="9999" class="form-control form-control-sm" value="<?= e($s['orden']) ?>" aria-label="Orden"></td>
                        <td class="text-center small"><?= e($s['usos']) ?></td>
                        <td><?= (int) $s['activo'] === 1 ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-light border">Inactivo</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <button form="<?= e($f) ?>" type="submit" class="btn btn-sm btn-outline-primary" title="Guardar cambios"><i class="fa-solid fa-floppy-disk"></i></button>
                            <button form="a-sw-<?= e($s['id']) ?>" type="submit" class="btn btn-sm <?= (int) $s['activo'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    title="<?= (int) $s['activo'] === 1 ? 'Desactivar' : 'Activar' ?>"><i class="fa-solid <?= (int) $s['activo'] === 1 ? 'fa-ban' : 'fa-rotate-left' ?>"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="table-light">
                        <td><input form="f-sw-nuevo" name="nombre" class="form-control form-control-sm" maxlength="100" placeholder="Nuevo software" required aria-label="Nombre del nuevo software"></td>
                        <td><input form="f-sw-nuevo" name="descripcion" class="form-control form-control-sm" maxlength="255" placeholder="Descripción (opcional)" aria-label="Descripción"></td>
                        <td><input form="f-sw-nuevo" name="orden" type="number" min="0" max="9999" class="form-control form-control-sm" value="0" aria-label="Orden"></td>
                        <td colspan="2"></td>
                        <td class="text-end"><button form="f-sw-nuevo" type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus me-1"></i>Agregar</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php foreach ($software as $s): ?>
            <form id="f-sw-<?= e($s['id']) ?>" method="post" action="<?= e(url('parametros/software/' . $s['id'])) ?>"><?= csrf_field() ?></form>
            <form id="a-sw-<?= e($s['id']) ?>" method="post" action="<?= e(url('parametros/software/' . $s['id'] . '/activo')) ?>"><?= csrf_field() ?></form>
        <?php endforeach; ?>
        <form id="f-sw-nuevo" method="post" action="<?= e(url('parametros/software')) ?>"><?= csrf_field() ?></form>

    <?php else: ?>
        <p class="small text-body-secondary">
            Actividades de las actas de mantenimiento, por categoría y tipo de equipo. Los ítems con código (p. ej. <code>FORMATEO_SO</code>)
            activan comportamientos del sistema: si desactiva el de formateo, las actas dejarán de preguntar por la licencia Microsoft 365.
        </p>
        <div class="table-responsive">
            <table class="table table-sm align-middle">
                <thead class="table-light"><tr><th style="width: 130px;">Categoría</th><th style="min-width: 320px;">Actividad</th><th>Aplica a</th><th style="width: 90px;">Orden</th><th class="text-center">Usos</th><th>Estado</th><th class="text-end" style="width: 150px;"></th></tr></thead>
                <tbody>
                <?php foreach ($checklist as $c):
                    $f = 'f-ck-' . $c['id'];
                    $aplica = explode(',', (string) $c['aplica_a']);
                    ?>
                    <tr class="<?= (int) $c['activo'] === 1 ? '' : 'text-body-secondary' ?>">
                        <td>
                            <select form="<?= e($f) ?>" name="categoria" class="form-select form-select-sm" aria-label="Categoría">
                                <?php foreach ($categorias as $cat): ?><option value="<?= e($cat) ?>" <?= $c['categoria'] === $cat ? 'selected' : '' ?>><?= e($nombresCat[$cat]) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <input form="<?= e($f) ?>" name="descripcion" class="form-control form-control-sm" maxlength="150" value="<?= e($c['descripcion']) ?>" required aria-label="Actividad">
                            <?php if ($c['codigo'] !== null): ?><span class="badge text-bg-light border font-monospace mt-1"><?= e($c['codigo']) ?></span><?php endif; ?>
                        </td>
                        <td class="text-nowrap">
                            <?php foreach ($tiposEquipo as $t): $idChk = 'ck-' . $c['id'] . '-' . $t; ?>
                                <div class="form-check form-check-inline small me-2">
                                    <input form="<?= e($f) ?>" class="form-check-input" type="checkbox" name="aplica_a[]" value="<?= e($t) ?>" id="<?= e($idChk) ?>" <?= in_array($t, $aplica, true) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="<?= e($idChk) ?>"><?= e(etiqueta($t)) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td><input form="<?= e($f) ?>" name="orden" type="number" min="0" max="9999" class="form-control form-control-sm" value="<?= e($c['orden']) ?>" aria-label="Orden"></td>
                        <td class="text-center small"><?= e($c['usos']) ?></td>
                        <td><?= (int) $c['activo'] === 1 ? '<span class="badge text-bg-success">Activo</span>' : '<span class="badge text-bg-light border">Inactivo</span>' ?></td>
                        <td class="text-end text-nowrap">
                            <button form="<?= e($f) ?>" type="submit" class="btn btn-sm btn-outline-primary" title="Guardar cambios"><i class="fa-solid fa-floppy-disk"></i></button>
                            <button form="a-ck-<?= e($c['id']) ?>" type="submit" class="btn btn-sm <?= (int) $c['activo'] === 1 ? 'btn-outline-danger' : 'btn-outline-success' ?>"
                                    title="<?= (int) $c['activo'] === 1 ? 'Desactivar' : 'Activar' ?>"><i class="fa-solid <?= (int) $c['activo'] === 1 ? 'fa-ban' : 'fa-rotate-left' ?>"></i></button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                    <tr class="table-light">
                        <td>
                            <select form="f-ck-nuevo" name="categoria" class="form-select form-select-sm" aria-label="Categoría">
                                <?php foreach ($categorias as $cat): ?><option value="<?= e($cat) ?>"><?= e($nombresCat[$cat]) ?></option><?php endforeach; ?>
                            </select>
                        </td>
                        <td><input form="f-ck-nuevo" name="descripcion" class="form-control form-control-sm" maxlength="150" placeholder="Nueva actividad" required aria-label="Nueva actividad"></td>
                        <td class="text-nowrap">
                            <?php foreach ($tiposEquipo as $t): ?>
                                <div class="form-check form-check-inline small me-2">
                                    <input form="f-ck-nuevo" class="form-check-input" type="checkbox" name="aplica_a[]" value="<?= e($t) ?>" id="ck-nuevo-<?= e($t) ?>" checked>
                                    <label class="form-check-label" for="ck-nuevo-<?= e($t) ?>"><?= e(etiqueta($t)) ?></label>
                                </div>
                            <?php endforeach; ?>
                        </td>
                        <td><input form="f-ck-nuevo" name="orden" type="number" min="0" max="9999" class="form-control form-control-sm" value="0" aria-label="Orden"></td>
                        <td colspan="2"></td>
                        <td class="text-end"><button form="f-ck-nuevo" type="submit" class="btn btn-sm btn-primary"><i class="fa-solid fa-plus me-1"></i>Agregar</button></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <?php foreach ($checklist as $c): ?>
            <form id="f-ck-<?= e($c['id']) ?>" method="post" action="<?= e(url('parametros/checklist/' . $c['id'])) ?>"><?= csrf_field() ?></form>
            <form id="a-ck-<?= e($c['id']) ?>" method="post" action="<?= e(url('parametros/checklist/' . $c['id'] . '/activo')) ?>"
                  data-confirm="<?= e($c['codigo'] === 'FORMATEO_SO' && (int) $c['activo'] === 1 ? '¿Desactivar el ítem de formateo? Las actas dejarán de preguntar qué hacer con la licencia Microsoft 365.' : '') ?>"><?= csrf_field() ?></form>
        <?php endforeach; ?>
        <form id="f-ck-nuevo" method="post" action="<?= e(url('parametros/checklist')) ?>"><?= csrf_field() ?></form>
    <?php endif; ?>
    </div>
</div>
