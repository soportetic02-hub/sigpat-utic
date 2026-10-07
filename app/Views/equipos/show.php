<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $equipo  fila de v_equipos_estado + campos propios
 * @var list<array<string, mixed>> $codigos
 * @var list<array<string, mixed>> $puntos
 * @var array<string, mixed>|null $licencia
 * @var list<array<string, mixed>> $historial
 * @var list<array<string, mixed>> $actas
 * @var list<array<string, mixed>> $informes
 * @var bool $eliminable
 */
$id = (int) $equipo['id'];
$esImpresora = $equipo['tipo'] === 'IMPRESORA';
$deBaja = $equipo['estado_operativo'] === 'DE_BAJA';
$recomendado = (int) $equipo['recomendado_baja'] === 1;
$esAdmin = has_role('ADMINISTRADOR');
$retraso = $equipo['dias_retraso_mantenimiento'] === null ? null : (int) $equipo['dias_retraso_mantenimiento'];
$vidaVencida = $equipo['fecha_sugerida_baja'] !== null && $equipo['fecha_sugerida_baja'] <= date('Y-m-d');
$iconos = ['PC' => 'fa-desktop', 'LAPTOP' => 'fa-laptop', 'IMPRESORA' => 'fa-print'];
$dato = static fn (mixed $valor): string => $valor === null || $valor === '' ? '—' : (string) $valor;
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">
            <i class="fa-solid <?= e($iconos[$equipo['tipo']] ?? 'fa-desktop') ?> me-2 text-primary"></i><?= e($equipo['marca'] . ' ' . $equipo['modelo']) ?>
        </h1>
        <span class="text-body-secondary small"><?= e(etiqueta($equipo['tipo'])) ?> #<?= e($id) ?></span>
        <span class="badge <?= e(clase_estado($equipo['estado_operativo'])) ?> ms-1"><?= e(etiqueta($equipo['estado_operativo'])) ?></span>
        <span class="badge <?= e(clase_condicion($equipo['condicion_fisica'])) ?>"><?= e(etiqueta($equipo['condicion_fisica'])) ?></span>
        <span class="badge text-bg-light border"><?= (int) $equipo['en_uso'] === 1 ? 'En uso' : 'Sin responsable' ?></span>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(url('equipos')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <?php if (!$deBaja): ?>
            <a href="<?= e(url('mantenimientos/crear', ['equipo_id' => $id])) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Registrar acta</a>
            <a href="<?= e(url('informes/crear', ['equipo_id' => $id])) ?>" class="btn btn-outline-primary"><i class="fa-solid fa-file-lines me-1"></i>Crear informe</a>
            <a href="<?= e(url('equipos/' . $id . '/editar')) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Editar</a>
        <?php endif; ?>
        <?php if ($esAdmin && $recomendado && !$deBaja): ?>
            <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#modal-baja"><i class="fa-solid fa-box-archive me-1"></i>Confirmar baja</button>
        <?php endif; ?>
        <?php if ($esAdmin && $eliminable): ?>
            <form method="post" action="<?= e(url('equipos/' . $id . '/eliminar')) ?>"
                  data-confirm="¿Eliminar definitivamente este equipo? Use esta opción solo para corregir un registro erróneo; la acción no se puede deshacer.">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger" title="Eliminar registro erróneo"><i class="fa-solid fa-trash"></i></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php if ($recomendado && !$deBaja): ?>
    <div class="alert alert-danger d-flex gap-2">
        <i class="fa-solid fa-triangle-exclamation mt-1"></i>
        <div>Un informe técnico emitido recomienda la <strong>baja definitiva</strong> de este equipo.
            <?= $esAdmin ? 'Puede confirmarla con el botón "Confirmar baja".' : 'Un administrador debe confirmarla.' ?></div>
    </div>
<?php endif; ?>
<?php if ($deBaja): ?>
    <div class="alert alert-secondary"><i class="fa-solid fa-box-archive me-1"></i>Equipo dado de baja el <?= e(fecha($equipo['fecha_baja'])) ?>. Su registro se conserva solo para consulta.</div>
<?php endif; ?>

<div class="row g-3">
    <!-- Indicadores del ciclo de vida -->
    <div class="col-12">
        <div class="row g-3">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-body-secondary">Antigüedad</div>
                    <div class="fs-5 fw-semibold"><?= $equipo['anios_antiguedad'] === null ? '—' : e($equipo['anios_antiguedad']) . ' año(s)' ?></div>
                    <div class="small text-body-secondary">Adquirido: <?= e(fecha($equipo['fecha_adquisicion']) ?: '—') ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-body-secondary">Fecha sugerida de baja</div>
                    <div class="fs-5 fw-semibold <?= $vidaVencida && !$deBaja ? 'text-danger' : '' ?>"><?= e(fecha($equipo['fecha_sugerida_baja']) ?: '—') ?></div>
                    <div class="small text-body-secondary">Vida útil: <?= e($equipo['vida_util_meses']) ?> meses<?= $vidaVencida && !$deBaja ? ' · cumplida' : '' ?></div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-body-secondary">Próximo mantenimiento</div>
                    <div class="fs-5 fw-semibold <?= $retraso !== null && $retraso > 0 && !$deBaja ? 'text-danger' : '' ?>"><?= e(fecha($equipo['fecha_proximo_mantenimiento']) ?: '—') ?></div>
                    <div class="small text-body-secondary">
                        <?php if ($retraso !== null && $retraso > 0 && !$deBaja): ?>Vencido hace <?= e($retraso) ?> día(s)
                        <?php else: ?>Cada <?= e($equipo['periodicidad_efectiva_meses']) ?> meses<?= $equipo['periodicidad_mant_meses'] === null ? ' (por defecto)' : '' ?><?php endif; ?>
                    </div>
                </div></div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100"><div class="card-body">
                    <div class="small text-body-secondary">Último mantenimiento</div>
                    <div class="fs-5 fw-semibold"><?= e(fecha($equipo['fecha_ultimo_mantenimiento']) ?: 'Nunca') ?></div>
                    <div class="small text-body-secondary"><?= e(count($actas)) ?> acta(s) registradas</div>
                </div></div>
            </div>
        </div>
    </div>

    <!-- Datos -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-microchip me-1"></i>Hardware</h2>
                <?php if ($esImpresora): ?>
                    <p class="small text-body-secondary mb-0">Las impresoras no registran procesador, RAM, disco ni sistema operativo.</p>
                <?php else: ?>
                    <dl class="row small mb-0">
                        <dt class="col-5">Procesador</dt><dd class="col-7"><?= e($dato($equipo['procesador'])) ?></dd>
                        <dt class="col-5">RAM</dt><dd class="col-7"><?= $equipo['ram_gb'] === null ? '—' : e($equipo['ram_gb']) . ' GB' ?></dd>
                        <dt class="col-5">Disco</dt><dd class="col-7"><?= $equipo['disco_capacidad_gb'] === null ? e($dato($equipo['disco_tipo'])) : e(trim(($equipo['disco_tipo'] ?? '') . ' ' . $equipo['disco_capacidad_gb'] . ' GB')) ?></dd>
                        <dt class="col-5">Sistema operativo</dt><dd class="col-7"><?= e($dato($equipo['sistema_operativo'])) ?></dd>
                    </dl>
                <?php endif; ?>

                <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-3"><i class="fa-solid fa-network-wired me-1"></i>Red</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Hostname</dt><dd class="col-7"><?= e($dato($equipo['hostname'])) ?></dd>
                    <dt class="col-5">IP</dt><dd class="col-7"><?= e($dato($equipo['ip_lan'])) ?></dd>
                    <dt class="col-5">MAC LAN</dt><dd class="col-7 font-monospace"><?= e($dato($equipo['mac_lan'])) ?></dd>
                    <dt class="col-5">MAC Wi-Fi</dt><dd class="col-7 font-monospace"><?= e($dato($equipo['mac_wifi'])) ?></dd>
                    <dt class="col-5">Puntos de red</dt>
                    <dd class="col-7">
                        <?php if ($puntos === []): ?>—<?php endif; ?>
                        <?php foreach ($puntos as $p): ?>
                            <div><span class="fw-semibold"><?= e($p['codigo_punto']) ?></span>
                                <?php if ($p['switch_puerto'] !== null || $p['vlan'] !== null): ?>
                                    <span class="text-body-secondary">(<?= e(trim(($p['switch_puerto'] ?? '') . ($p['vlan'] !== null ? ' · VLAN ' . $p['vlan'] : ''), ' ·')) ?>)</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-barcode me-1"></i>Control patrimonial</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">N° de serie</dt><dd class="col-7"><?= e($dato($equipo['nro_serie'])) ?></dd>
                    <dt class="col-5">Código patrimonial</dt><dd class="col-7"><?= e($dato($equipo['codigo_patrimonial'])) ?></dd>
                    <dt class="col-5">Código interno</dt><dd class="col-7"><?= e($dato($equipo['codigo_interno'])) ?></dd>
                    <dt class="col-5">Códigos anuales</dt>
                    <dd class="col-7">
                        <?php if ($codigos === []): ?>—<?php endif; ?>
                        <?php foreach ($codigos as $c): ?><div><span class="badge text-bg-light border"><?= e($c['anio']) ?></span> <?= e($c['codigo']) ?></div><?php endforeach; ?>
                    </dd>
                    <dt class="col-5">Orden de compra</dt><dd class="col-7"><?= e($dato($equipo['orden_compra'])) ?></dd>
                    <dt class="col-5">Proveedor</dt><dd class="col-7"><?= e($dato($equipo['proveedor'])) ?></dd>
                    <dt class="col-5">Valor</dt><dd class="col-7"><?= $equipo['valor_adquisicion'] === null ? '—' : 'S/ ' . e(number_format((float) $equipo['valor_adquisicion'], 2)) ?></dd>
                    <dt class="col-5">Garantía hasta</dt><dd class="col-7"><?= e(fecha($equipo['garantia_hasta']) ?: '—') ?></dd>
                </dl>

                <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-3"><i class="fa-solid fa-user-tag me-1"></i>Asignación actual</h2>
                <dl class="row small mb-0">
                    <dt class="col-5">Oficina</dt><dd class="col-7"><?= e($equipo['oficina_nombre']) ?></dd>
                    <dt class="col-5">Responsable</dt>
                    <dd class="col-7">
                        <?php if ($equipo['personal_id'] === null): ?>Sin responsable
                        <?php else: ?><a href="<?= e(url('personal/' . $equipo['personal_id'])) ?>"><?= e($equipo['personal_nombre']) ?></a>
                            <?php if ($equipo['personal_cargo'] !== null): ?><div class="text-body-secondary"><?= e($equipo['personal_cargo']) ?></div><?php endif; ?>
                        <?php endif; ?>
                    </dd>
                </dl>

                <h2 class="h6 text-uppercase text-body-secondary mt-4 mb-3"><i class="fa-brands fa-microsoft me-1"></i>Licencia Microsoft 365</h2>
                <?php if ($esImpresora): ?>
                    <p class="small text-body-secondary mb-0">No aplica a impresoras.</p>
                <?php elseif ($licencia === null): ?>
                    <p class="small text-body-secondary mb-0">No tiene Office instalado con ninguna cuenta Microsoft 365.</p>
                <?php else: ?>
                    <dl class="row small mb-0">
                        <dt class="col-5">Cuenta</dt>
                        <dd class="col-7 text-break"><a href="<?= e(url('licencias/' . $licencia['licencia_id'])) ?>"><?= e($licencia['correo']) ?></a></dd>
                        <dt class="col-5">Código</dt><dd class="col-7"><?= e($licencia['codigo']) ?></dd>
                        <dt class="col-5">Instalación</dt>
                        <dd class="col-7">
                            Slot <?= e($licencia['slot']) ?> · desde <?= e(fecha((string) $licencia['fecha_asignacion'])) ?>
                            <?php if ($licencia['estado_verificacion'] === 'POR_VERIFICAR'): ?><span class="badge text-bg-warning ms-1">Por verificar</span><?php endif; ?>
                        </dd>
                    </dl>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($equipo['observaciones'] !== null): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm"><div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-2">Observaciones</h2>
                <p class="small mb-0" style="white-space: pre-line;"><?= e($equipo['observaciones']) ?></p>
            </div></div>
        </div>
    <?php endif; ?>

    <!-- Historial -->
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-clock-rotate-left me-1"></i>Historial de asignaciones</h2>
                <?php if ($historial === []): ?>
                    <p class="small text-body-secondary mb-0">Sin movimientos registrados.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Desde</th><th>Hasta</th><th>Oficina</th><th>Responsable</th><th>Motivo</th><th class="d-none d-lg-table-cell">Observación</th><th class="d-none d-md-table-cell">Registró</th></tr></thead>
                            <tbody>
                            <?php foreach ($historial as $h): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= e(fecha((string) $h['fecha_inicio'], true)) ?></td>
                                    <td class="small text-nowrap"><?= $h['fecha_fin'] === null ? '<span class="badge text-bg-success">Vigente</span>' : e(fecha((string) $h['fecha_fin'], true)) ?></td>
                                    <td class="small"><?= e($h['oficina_nombre']) ?></td>
                                    <td class="small"><?= e($h['personal_nombre'] ?? '—') ?></td>
                                    <td><span class="badge text-bg-light border"><?= e($h['motivo']) ?></span></td>
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

    <!-- Actas e informes -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-screwdriver-wrench me-1"></i>Actas de mantenimiento</h2>
                <?php if ($actas === []): ?>
                    <p class="small text-body-secondary mb-0">Sin actas registradas.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>N°</th><th>Tipo</th><th>Ingreso</th><th>Estado</th><th>Técnico</th></tr></thead>
                        <tbody>
                        <?php foreach ($actas as $a): ?>
                            <tr>
                                <td class="small"><a href="<?= e(url('mantenimientos/' . $a['id'])) ?>"><?= e($a['numero'] ?? 'Borrador #' . $a['id']) ?></a></td>
                                <td class="small"><?= e(etiqueta($a['tipo'])) ?></td>
                                <td class="small"><?= e(fecha((string) $a['fecha_ingreso'])) ?></td>
                                <td><span class="badge <?= $a['estado'] === 'CERRADA' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($a['estado'])) ?></span></td>
                                <td class="small"><?= e($a['tecnico']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-file-lines me-1"></i>Informes técnicos</h2>
                <?php if ($informes === []): ?>
                    <p class="small text-body-secondary mb-0">Sin informes registrados.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <thead class="table-light"><tr><th>N°</th><th>Fecha</th><th>Acción</th><th>Estado</th></tr></thead>
                        <tbody>
                        <?php foreach ($informes as $i): ?>
                            <tr>
                                <td class="small"><a href="<?= e(url('informes/' . $i['id'])) ?>"><?= e($i['numero'] ?? 'Borrador #' . $i['id']) ?></a><div class="text-body-secondary"><?= e($i['asunto']) ?></div></td>
                                <td class="small"><?= e(fecha((string) $i['fecha'])) ?></td>
                                <td class="small"><?= e(etiqueta($i['accion_requerida'])) ?></td>
                                <td><span class="badge <?= $i['estado'] === 'EMITIDO' ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($i['estado'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 small text-body-secondary">
        Registrado el <?= e(fecha((string) $equipo['created_at'], true)) ?><?= $equipo['creado_por'] !== null ? ' por ' . e($equipo['creado_por']) : '' ?>
        · Última modificación <?= e(fecha((string) $equipo['updated_at'], true)) ?><?= $equipo['actualizado_por'] !== null ? ' por ' . e($equipo['actualizado_por']) : '' ?>
    </div>
</div>

<?php if ($esAdmin && $recomendado && !$deBaja): ?>
<div class="modal fade" id="modal-baja" tabindex="-1" aria-labelledby="modal-baja-titulo" aria-hidden="true">
    <div class="modal-dialog">
        <form method="post" action="<?= e(url('equipos/' . $id . '/baja')) ?>" class="modal-content">
            <?= csrf_field() ?>
            <div class="modal-header">
                <h2 class="modal-title h5" id="modal-baja-titulo"><i class="fa-solid fa-box-archive me-2 text-danger"></i>Confirmar baja definitiva</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p>Al confirmar, en una sola operación:</p>
                <ul class="small">
                    <li>El equipo pasa a <strong>DE BAJA</strong> y ya no podrá modificarse.</li>
                    <?php if ($licencia !== null): ?><li>Se libera su instalación de la cuenta <?= e($licencia['correo']) ?> (slot <?= e($licencia['slot']) ?>) con motivo BAJA.</li><?php endif; ?>
                    <?php if ($equipo['personal_id'] !== null): ?><li>Se desvincula a <?= e($equipo['personal_nombre']) ?> como responsable.</li><?php endif; ?>
                    <li>Queda registro en el historial y en la auditoría.</li>
                </ul>
                <label for="observacion-baja" class="form-label">Observación (opcional)</label>
                <textarea id="observacion-baja" name="observacion" class="form-control" rows="2" maxlength="200" placeholder="p. ej. Según Informe N° 005-2026/OTIC"></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn btn-danger"><i class="fa-solid fa-box-archive me-1"></i>Confirmar baja</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
