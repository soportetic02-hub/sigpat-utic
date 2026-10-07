<?php

declare(strict_types=1);

/**
 * Alta y edición de equipos, en pestañas.
 *
 * @var array<string, mixed>|null $equipo  null = alta
 * @var int|null $oficinaId
 * @var int|null $personalId
 * @var list<array<string, mixed>> $codigos
 * @var list<array<string, mixed>> $puntos
 * @var int $vidaUtilDef
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 * @var list<array{id: int, nombre: string, oficina_id: int, oficina_nombre: string}> $personal
 * @var list<string> $tipos
 * @var list<string> $condiciones
 * @var list<string> $estados
 * @var list<string> $discos
 */
$esNuevo = $equipo === null;
$v = static fn (string $c, mixed $def = ''): string => old($c, $equipo[$c] ?? $def);
$cls = static fn (string $c, string $base = 'form-control'): string => $base . (error($c) !== null ? ' is-invalid' : '');
$fb = static fn (string $c): string => '<div class="invalid-feedback">' . e(error($c)) . '</div>';

// Filas dinámicas: tras un error se reconstruyen desde lo enviado; si no, desde la BD.
if (hay_old()) {
    $filasCodigos = [];
    foreach (old_array('codigos_anio') as $i => $anio) {
        $filasCodigos[] = ['anio' => $anio, 'codigo' => old_array('codigos_codigo')[$i] ?? ''];
    }
    $filasPuntos = [];
    foreach (old_array('puntos_codigo') as $i => $cod) {
        $filasPuntos[] = [
            'codigo_punto'  => $cod,
            'switch_puerto' => old_array('puntos_switch')[$i] ?? '',
            'vlan'          => old_array('puntos_vlan')[$i] ?? '',
            'observacion'   => old_array('puntos_obs')[$i] ?? '',
        ];
    }
} else {
    $filasCodigos = $codigos;
    $filasPuntos = $puntos;
}

$pestanas = [
    'hardware'    => ['Hardware', 'fa-microchip', ['procesador', 'ram_gb', 'disco_tipo', 'disco_capacidad_gb', 'sistema_operativo']],
    'red'         => ['Red', 'fa-network-wired', ['hostname', 'ip_lan', 'mac_lan', 'mac_wifi', 'puntos_red']],
    'patrimonial' => ['Control patrimonial', 'fa-barcode', ['nro_serie', 'codigo_patrimonial', 'codigo_interno', 'codigos', 'orden_compra', 'proveedor', 'valor_adquisicion']],
    'ciclo'       => ['Ciclo de vida', 'fa-hourglass-half', ['fecha_adquisicion', 'vida_util_meses', 'garantia_hasta', 'periodicidad_mant_meses', 'estado_operativo', 'condicion_fisica', 'observaciones']],
    'asignacion'  => ['Asignación', 'fa-user-tag', ['oficina_id', 'personal_id']],
];
$conError = [];
foreach ($pestanas as $clave => [, , $campos]) {
    foreach ($campos as $campo) {
        if (error($campo) !== null) {
            $conError[$clave] = true;
        }
    }
}
$activa = array_key_first($conError) ?? 'hardware';
$enMantenimiento = !$esNuevo && $equipo['estado_operativo'] === 'EN_MANTENIMIENTO';
$accion = $esNuevo ? url('equipos') : url('equipos/' . $equipo['id']);
$volver = $esNuevo ? url('equipos') : url('equipos/' . $equipo['id']);
$personalSel = old('personal_id', $personalId === null ? '' : (string) $personalId);
$oficinaSel = old('oficina_id', $oficinaId === null ? '' : (string) $oficinaId);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-desktop me-2 text-primary"></i><?= e($esNuevo ? 'Registrar equipo' : 'Editar equipo #' . $equipo['id']) ?></h1>
    <a href="<?= e($volver) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<form method="post" action="<?= e($accion) ?>" novalidate id="form-equipo">
    <?= csrf_field() ?>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select id="tipo" name="tipo" class="<?= e($cls('tipo', 'form-select')) ?>" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= e($t) ?>" <?= $v('tipo') === $t ? 'selected' : '' ?>><?= e(etiqueta($t)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $fb('tipo') ?>
                </div>
                <div class="col-md-4">
                    <label for="marca" class="form-label">Marca <span class="text-danger">*</span></label>
                    <input type="text" id="marca" name="marca" class="<?= e($cls('marca')) ?>" maxlength="60" value="<?= e($v('marca')) ?>" required>
                    <?= $fb('marca') ?>
                </div>
                <div class="col-md-5">
                    <label for="modelo" class="form-label">Modelo <span class="text-danger">*</span></label>
                    <input type="text" id="modelo" name="modelo" class="<?= e($cls('modelo')) ?>" maxlength="100" value="<?= e($v('modelo')) ?>" required>
                    <?= $fb('modelo') ?>
                </div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" role="tablist">
        <?php foreach ($pestanas as $clave => [$titulo, $icono]): ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?= $clave === $activa ? 'active' : '' ?>" id="tab-<?= e($clave) ?>" data-bs-toggle="tab"
                        data-bs-target="#panel-<?= e($clave) ?>" type="button" role="tab" aria-controls="panel-<?= e($clave) ?>"
                        aria-selected="<?= $clave === $activa ? 'true' : 'false' ?>">
                    <i class="fa-solid <?= e($icono) ?> me-1"></i><?= e($titulo) ?>
                    <?php if (isset($conError[$clave])): ?><span class="badge text-bg-danger ms-1">!</span><?php endif; ?>
                </button>
            </li>
        <?php endforeach; ?>
    </ul>

    <div class="card shadow-sm border-0 border-top-0 rounded-top-0">
        <div class="card-body tab-content">
            <!-- Hardware -->
            <div class="tab-pane fade <?= $activa === 'hardware' ? 'show active' : '' ?>" id="panel-hardware" role="tabpanel" aria-labelledby="tab-hardware">
                <div class="alert alert-info small d-none" id="aviso-impresora">
                    <i class="fa-solid fa-print me-1"></i>Las impresoras no registran procesador, RAM, disco ni sistema operativo.
                </div>
                <fieldset id="campos-hardware" class="row g-3">
                    <div class="col-md-6">
                        <label for="procesador" class="form-label">Procesador</label>
                        <input type="text" id="procesador" name="procesador" class="<?= e($cls('procesador')) ?>" maxlength="120"
                               placeholder="p. ej. Intel Core i5-10500 3.10 GHz" value="<?= e($v('procesador')) ?>">
                        <?= $fb('procesador') ?>
                    </div>
                    <div class="col-md-2">
                        <label for="ram_gb" class="form-label">RAM (GB)</label>
                        <input type="number" id="ram_gb" name="ram_gb" class="<?= e($cls('ram_gb')) ?>" min="1" max="1024" value="<?= e($v('ram_gb')) ?>">
                        <?= $fb('ram_gb') ?>
                    </div>
                    <div class="col-md-2">
                        <label for="disco_tipo" class="form-label">Disco</label>
                        <select id="disco_tipo" name="disco_tipo" class="<?= e($cls('disco_tipo', 'form-select')) ?>">
                            <option value="">—</option>
                            <?php foreach ($discos as $d): ?>
                                <option value="<?= e($d) ?>" <?= $v('disco_tipo') === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fb('disco_tipo') ?>
                    </div>
                    <div class="col-md-2">
                        <label for="disco_capacidad_gb" class="form-label">Capacidad (GB)</label>
                        <input type="number" id="disco_capacidad_gb" name="disco_capacidad_gb" class="<?= e($cls('disco_capacidad_gb')) ?>" min="1" max="100000" value="<?= e($v('disco_capacidad_gb')) ?>">
                        <?= $fb('disco_capacidad_gb') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="sistema_operativo" class="form-label">Sistema operativo</label>
                        <input type="text" id="sistema_operativo" name="sistema_operativo" class="<?= e($cls('sistema_operativo')) ?>" maxlength="80"
                               placeholder="p. ej. Windows 11 Pro 64 bits" value="<?= e($v('sistema_operativo')) ?>">
                        <?= $fb('sistema_operativo') ?>
                    </div>
                </fieldset>
            </div>

            <!-- Red -->
            <div class="tab-pane fade <?= $activa === 'red' ? 'show active' : '' ?>" id="panel-red" role="tabpanel" aria-labelledby="tab-red">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="hostname" class="form-label">Hostname</label>
                        <input type="text" id="hostname" name="hostname" class="<?= e($cls('hostname')) ?> text-uppercase" maxlength="63" value="<?= e($v('hostname')) ?>">
                        <?= $fb('hostname') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="ip_lan" class="form-label">IP (v4 o v6)</label>
                        <input type="text" id="ip_lan" name="ip_lan" class="<?= e($cls('ip_lan')) ?>" maxlength="45" placeholder="10.10.1.25" value="<?= e($v('ip_lan')) ?>">
                        <?= $fb('ip_lan') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="mac_lan" class="form-label">MAC LAN</label>
                        <input type="text" id="mac_lan" name="mac_lan" class="<?= e($cls('mac_lan')) ?> text-uppercase" maxlength="17" placeholder="00:1A:2B:3C:4D:5E" value="<?= e($v('mac_lan')) ?>">
                        <?= $fb('mac_lan') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="mac_wifi" class="form-label">MAC Wi-Fi</label>
                        <input type="text" id="mac_wifi" name="mac_wifi" class="<?= e($cls('mac_wifi')) ?> text-uppercase" maxlength="17" value="<?= e($v('mac_wifi')) ?>">
                        <?= $fb('mac_wifi') ?>
                    </div>
                </div>
                <p class="small text-body-secondary mt-2 mb-3">La MAC se guarda normalizada (AA:BB:CC:DD:EE:FF) aunque la escriba con guiones o sin separadores.</p>

                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h2 class="h6 mb-0">Puntos de red</h2>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-agregar-fila="puntos"><i class="fa-solid fa-plus me-1"></i>Agregar punto</button>
                </div>
                <?php if (error('puntos_red') !== null): ?><div class="alert alert-danger py-2 small"><?= e(error('puntos_red')) ?></div><?php endif; ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>Código / rotulado</th><th>Switch / puerto</th><th>VLAN</th><th>Observación</th><th></th></tr></thead>
                        <tbody id="filas-puntos">
                        <?php foreach ($filasPuntos as $p): ?>
                            <tr>
                                <td><input type="text" name="puntos_codigo[]" class="form-control form-control-sm text-uppercase" maxlength="30" value="<?= e($p['codigo_punto']) ?>"></td>
                                <td><input type="text" name="puntos_switch[]" class="form-control form-control-sm" maxlength="60" value="<?= e($p['switch_puerto']) ?>"></td>
                                <td><input type="text" name="puntos_vlan[]" class="form-control form-control-sm" maxlength="20" value="<?= e($p['vlan']) ?>"></td>
                                <td><input type="text" name="puntos_obs[]" class="form-control form-control-sm" maxlength="255" value="<?= e($p['observacion']) ?>"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-fila title="Quitar"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-body-secondary mb-0 <?= $filasPuntos === [] ? '' : 'd-none' ?>" data-vacio="puntos">Sin puntos de red registrados.</p>
            </div>

            <!-- Control patrimonial -->
            <div class="tab-pane fade <?= $activa === 'patrimonial' ? 'show active' : '' ?>" id="panel-patrimonial" role="tabpanel" aria-labelledby="tab-patrimonial">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label for="nro_serie" class="form-label">N° de serie</label>
                        <input type="text" id="nro_serie" name="nro_serie" class="<?= e($cls('nro_serie')) ?> text-uppercase" maxlength="80" value="<?= e($v('nro_serie')) ?>">
                        <?= $fb('nro_serie') ?>
                    </div>
                    <div class="col-md-4">
                        <label for="codigo_patrimonial" class="form-label">Código patrimonial</label>
                        <input type="text" id="codigo_patrimonial" name="codigo_patrimonial" class="<?= e($cls('codigo_patrimonial')) ?>" maxlength="30" value="<?= e($v('codigo_patrimonial')) ?>">
                        <?= $fb('codigo_patrimonial') ?>
                    </div>
                    <div class="col-md-4">
                        <label for="codigo_interno" class="form-label">Código interno</label>
                        <input type="text" id="codigo_interno" name="codigo_interno" class="<?= e($cls('codigo_interno')) ?> text-uppercase" maxlength="30" value="<?= e($v('codigo_interno')) ?>">
                        <?= $fb('codigo_interno') ?>
                    </div>
                    <div class="col-md-4">
                        <label for="orden_compra" class="form-label">Orden de compra</label>
                        <input type="text" id="orden_compra" name="orden_compra" class="<?= e($cls('orden_compra')) ?>" maxlength="50" value="<?= e($v('orden_compra')) ?>">
                        <?= $fb('orden_compra') ?>
                    </div>
                    <div class="col-md-5">
                        <label for="proveedor" class="form-label">Proveedor</label>
                        <input type="text" id="proveedor" name="proveedor" class="<?= e($cls('proveedor')) ?>" maxlength="150" value="<?= e($v('proveedor')) ?>">
                        <?= $fb('proveedor') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="valor_adquisicion" class="form-label">Valor de adquisición (S/)</label>
                        <input type="text" id="valor_adquisicion" name="valor_adquisicion" class="<?= e($cls('valor_adquisicion')) ?>" inputmode="decimal" value="<?= e($v('valor_adquisicion')) ?>">
                        <?= $fb('valor_adquisicion') ?>
                    </div>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-4 mb-2">
                    <h2 class="h6 mb-0">Códigos anuales de inventario</h2>
                    <button type="button" class="btn btn-sm btn-outline-primary" data-agregar-fila="codigos"><i class="fa-solid fa-plus me-1"></i>Agregar año</button>
                </div>
                <?php if (error('codigos') !== null): ?><div class="alert alert-danger py-2 small"><?= e(error('codigos')) ?></div><?php endif; ?>
                <div class="table-responsive" style="max-width: 560px;">
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th style="width: 120px;">Año</th><th>Código</th><th></th></tr></thead>
                        <tbody id="filas-codigos">
                        <?php foreach ($filasCodigos as $c): ?>
                            <tr>
                                <td><input type="number" name="codigos_anio[]" class="form-control form-control-sm" min="1990" max="2100" value="<?= e($c['anio']) ?>"></td>
                                <td><input type="text" name="codigos_codigo[]" class="form-control form-control-sm text-uppercase" maxlength="40" value="<?= e($c['codigo']) ?>"></td>
                                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-fila title="Quitar"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="small text-body-secondary mb-0 <?= $filasCodigos === [] ? '' : 'd-none' ?>" data-vacio="codigos">Sin códigos anuales registrados.</p>
            </div>

            <!-- Ciclo de vida -->
            <div class="tab-pane fade <?= $activa === 'ciclo' ? 'show active' : '' ?>" id="panel-ciclo" role="tabpanel" aria-labelledby="tab-ciclo">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label for="fecha_adquisicion" class="form-label">Fecha de adquisición</label>
                        <input type="date" id="fecha_adquisicion" name="fecha_adquisicion" class="<?= e($cls('fecha_adquisicion')) ?>" max="<?= e(date('Y-m-d')) ?>" value="<?= e($v('fecha_adquisicion')) ?>">
                        <?= $fb('fecha_adquisicion') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="vida_util_meses" class="form-label">Vida útil (meses)</label>
                        <input type="number" id="vida_util_meses" name="vida_util_meses" class="<?= e($cls('vida_util_meses')) ?>" min="1" max="600" value="<?= e($v('vida_util_meses', (string) $vidaUtilDef)) ?>">
                        <?= $fb('vida_util_meses') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="garantia_hasta" class="form-label">Garantía hasta</label>
                        <input type="date" id="garantia_hasta" name="garantia_hasta" class="<?= e($cls('garantia_hasta')) ?>" value="<?= e($v('garantia_hasta')) ?>">
                        <?= $fb('garantia_hasta') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="periodicidad_mant_meses" class="form-label">Mantenimiento cada (meses)</label>
                        <input type="number" id="periodicidad_mant_meses" name="periodicidad_mant_meses" class="<?= e($cls('periodicidad_mant_meses')) ?>" min="1" max="60"
                               placeholder="Por defecto del sistema" value="<?= e($v('periodicidad_mant_meses')) ?>">
                        <?= $fb('periodicidad_mant_meses') ?>
                    </div>
                    <div class="col-md-3">
                        <label for="estado_operativo" class="form-label">Estado operativo <span class="text-danger">*</span></label>
                        <?php if ($enMantenimiento): ?>
                            <input type="text" class="form-control" value="En mantenimiento" disabled>
                            <input type="hidden" name="estado_operativo" value="EN_MANTENIMIENTO">
                            <div class="form-text">Lo cambia el cierre del acta de mantenimiento.</div>
                        <?php else: ?>
                            <select id="estado_operativo" name="estado_operativo" class="<?= e($cls('estado_operativo', 'form-select')) ?>">
                                <?php foreach ($estados as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $v('estado_operativo', 'OPERATIVO') === $s ? 'selected' : '' ?>><?= e(etiqueta($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?= $fb('estado_operativo') ?>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-3">
                        <label for="condicion_fisica" class="form-label">Condición física <span class="text-danger">*</span></label>
                        <select id="condicion_fisica" name="condicion_fisica" class="<?= e($cls('condicion_fisica', 'form-select')) ?>">
                            <?php foreach ($condiciones as $c): ?>
                                <option value="<?= e($c) ?>" <?= $v('condicion_fisica', 'BUENO') === $c ? 'selected' : '' ?>><?= e(etiqueta($c)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fb('condicion_fisica') ?>
                    </div>
                    <div class="col-12">
                        <label for="observaciones" class="form-label">Observaciones</label>
                        <textarea id="observaciones" name="observaciones" class="<?= e($cls('observaciones')) ?>" rows="3" maxlength="2000"><?= e($v('observaciones')) ?></textarea>
                        <?= $fb('observaciones') ?>
                    </div>
                </div>
            </div>

            <!-- Asignación -->
            <div class="tab-pane fade <?= $activa === 'asignacion' ? 'show active' : '' ?>" id="panel-asignacion" role="tabpanel" aria-labelledby="tab-asignacion">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="oficina_id" class="form-label">Oficina (ubicación) <span class="text-danger">*</span></label>
                        <select id="oficina_id" name="oficina_id" class="<?= e($cls('oficina_id', 'form-select')) ?>" required>
                            <option value="">— Seleccione —</option>
                            <?php foreach ($oficinas as $op): ?>
                                <option value="<?= e($op['id']) ?>" <?= $oficinaSel === (string) $op['id'] ? 'selected' : '' ?>>
                                    <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?= $fb('oficina_id') ?>
                    </div>
                    <div class="col-md-6">
                        <label for="personal_id" class="form-label">Responsable</label>
                        <select id="personal_id" name="personal_id" class="<?= e($cls('personal_id', 'form-select')) ?>">
                            <option value="">— Sin responsable (p. ej. impresora compartida) —</option>
                            <?php
                            $grupo = null;
                            foreach ($personal as $p):
                                if ($grupo !== $p['oficina_nombre']):
                                    if ($grupo !== null): ?></optgroup><?php endif;
                                    $grupo = $p['oficina_nombre']; ?>
                                    <optgroup label="<?= e($grupo) ?>">
                                <?php endif; ?>
                                <option value="<?= e($p['id']) ?>" data-oficina="<?= e($p['oficina_id']) ?>" <?= $personalSel === (string) $p['id'] ? 'selected' : '' ?>><?= e($p['nombre']) ?></option>
                            <?php endforeach;
                            if ($grupo !== null): ?></optgroup><?php endif; ?>
                        </select>
                        <?= $fb('personal_id') ?>
                        <div class="form-text" id="sugerencia-oficina"></div>
                    </div>
                </div>
                <?php if (!$esNuevo): ?>
                    <p class="small text-body-secondary mt-3 mb-0">
                        <i class="fa-solid fa-clock-rotate-left me-1"></i>Si cambia la oficina o el responsable, el movimiento se registra en el historial de asignaciones.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end gap-2 mt-3">
        <a href="<?= e($volver) ?>" class="btn btn-outline-secondary">Cancelar</a>
        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
    </div>
</form>

<template id="tpl-codigos">
    <tr>
        <td><input type="number" name="codigos_anio[]" class="form-control form-control-sm" min="1990" max="2100" value="<?= e(date('Y')) ?>"></td>
        <td><input type="text" name="codigos_codigo[]" class="form-control form-control-sm text-uppercase" maxlength="40"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-fila title="Quitar"><i class="fa-solid fa-trash"></i></button></td>
    </tr>
</template>
<template id="tpl-puntos">
    <tr>
        <td><input type="text" name="puntos_codigo[]" class="form-control form-control-sm text-uppercase" maxlength="30"></td>
        <td><input type="text" name="puntos_switch[]" class="form-control form-control-sm" maxlength="60"></td>
        <td><input type="text" name="puntos_vlan[]" class="form-control form-control-sm" maxlength="20"></td>
        <td><input type="text" name="puntos_obs[]" class="form-control form-control-sm" maxlength="255"></td>
        <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-quitar-fila title="Quitar"><i class="fa-solid fa-trash"></i></button></td>
    </tr>
</template>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var tipo = document.getElementById('tipo');
    var hardware = document.getElementById('campos-hardware');
    var aviso = document.getElementById('aviso-impresora');

    // Impresoras: campos de hardware ocultos y deshabilitados (no se envían).
    function aplicarTipo() {
        var esImpresora = tipo.value === 'IMPRESORA';
        hardware.disabled = esImpresora;
        hardware.classList.toggle('d-none', esImpresora);
        aviso.classList.toggle('d-none', !esImpresora);
    }
    tipo.addEventListener('change', aplicarTipo);
    aplicarTipo();

    // Filas dinámicas (códigos anuales y puntos de red).
    function actualizarVacio(nombre) {
        var cuerpo = document.getElementById('filas-' + nombre);
        var vacio = document.querySelector('[data-vacio="' + nombre + '"]');
        if (vacio) {
            vacio.classList.toggle('d-none', cuerpo.children.length > 0);
        }
    }
    document.querySelectorAll('[data-agregar-fila]').forEach(function (boton) {
        boton.addEventListener('click', function () {
            var nombre = boton.getAttribute('data-agregar-fila');
            var fila = document.getElementById('tpl-' + nombre).content.cloneNode(true);
            document.getElementById('filas-' + nombre).appendChild(fila);
            actualizarVacio(nombre);
        });
    });
    document.getElementById('form-equipo').addEventListener('click', function (evento) {
        var boton = evento.target.closest('[data-quitar-fila]');
        if (!boton) {
            return;
        }
        var cuerpo = boton.closest('tbody');
        boton.closest('tr').remove();
        actualizarVacio(cuerpo.id.replace('filas-', ''));
    });

    // Sugerencia: si el responsable está en otra oficina, ofrecer usar la suya.
    var oficina = document.getElementById('oficina_id');
    var personal = document.getElementById('personal_id');
    var sugerencia = document.getElementById('sugerencia-oficina');
    function revisarOficina() {
        sugerencia.textContent = '';
        var opcion = personal.options[personal.selectedIndex];
        if (!opcion || !opcion.dataset.oficina || opcion.dataset.oficina === oficina.value) {
            return;
        }
        var enlace = document.createElement('a');
        enlace.href = '#';
        enlace.textContent = 'Usar la oficina del responsable';
        enlace.addEventListener('click', function (ev) {
            ev.preventDefault();
            oficina.value = opcion.dataset.oficina;
            revisarOficina();
        });
        sugerencia.appendChild(document.createTextNode('El responsable pertenece a otra oficina. '));
        sugerencia.appendChild(enlace);
    }
    personal.addEventListener('change', revisarOficina);
    oficina.addEventListener('change', revisarOficina);
    revisarOficina();
});
</script>
