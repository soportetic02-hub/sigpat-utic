<?php

declare(strict_types=1);

/**
 * @var array<string, mixed> $acta
 * @var array<string, list<array<string, mixed>>> $checklist
 * @var array<string, array{accion: string, detalle: ?string}> $componentes
 * @var list<array<string, mixed>> $software
 * @var bool $puedeAnular
 * @var bool $puedeEliminar
 * @var bool $puedeFirmar
 * @var bool $requiereFirma
 * @var ?string $correoJefa
 * @var bool $correoConfigurado
 * @var list<array<string, mixed>> $envios
 */
$id = (int) $acta['id'];
$cerrada = $acta['estado'] === 'CERRADA';
$firmada = $acta['pdf_firmado_ruta'] !== null;
$puedeEnviar = $cerrada && (!$requiereFirma || $firmada);
// La firma con ReFirma solo se muestra si es obligatoria, si ya existe o si el usuario puede firmar.
$mostrarFirma = $requiereFirma || $firmada || $puedeFirmar;
$nombresCat = ['FISICO' => 'Físico', 'LOGICO' => 'Lógico', 'RED' => 'Red'];
$usuario = $acta['personal_nombres'] !== null ? $acta['personal_nombres'] . ' ' . $acta['personal_apellidos'] : 'Sin responsable';
$componentesUsados = array_filter($componentes, static fn (array $c): bool => $c['accion'] !== 'NO_APLICA');
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
    <div>
        <h1 class="h4 mb-1">
            <i class="fa-solid fa-screwdriver-wrench me-2 text-primary"></i>
            <?= $cerrada ? 'Acta N° ' . e($acta['numero']) : 'Acta en borrador #' . e($id) ?>
        </h1>
        <span class="badge <?= $cerrada ? 'text-bg-success' : 'text-bg-warning' ?>"><?= e(etiqueta($acta['estado'])) ?></span>
        <span class="badge <?= $acta['tipo'] === 'PREVENTIVO' ? 'text-bg-info' : 'text-bg-warning' ?>"><?= e(etiqueta($acta['tipo'])) ?></span>
        <?php if ($cerrada): ?>
            <?php if ($mostrarFirma): ?>
                <span class="badge <?= $firmada ? 'text-bg-success' : 'text-bg-light border' ?>"><i class="fa-solid fa-signature me-1"></i><?= $firmada ? 'Firmada' : 'Sin firma' ?></span>
            <?php endif; ?>
            <span class="badge <?= e(clase_envio($acta['estado_envio'])) ?>"><i class="fa-solid fa-envelope me-1"></i><?= e(etiqueta($acta['estado_envio'])) ?></span>
        <?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="<?= e(url('mantenimientos')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
        <?php if ($cerrada): ?>
            <a href="<?= e(url('mantenimientos/' . $id . '/pdf')) ?>" class="btn btn-outline-danger" target="_blank" rel="noopener"><i class="fa-solid fa-eye me-1"></i>Vista previa</a>
            <a href="<?= e(url('mantenimientos/' . $id . '/descargar')) ?>" class="btn btn-danger"><i class="fa-solid fa-file-pdf me-1"></i><?= $firmada ? 'Descargar PDF firmado' : 'Descargar PDF' ?></a>
            <?php if ($puedeEliminar): ?>
                <form method="post" action="<?= e(url('mantenimientos/' . $id . '/eliminar')) ?>"
                      data-confirm="<?= e('¿Eliminar el acta N° ' . $acta['numero'] . '? Se borrarán el acta, sus envíos y su PDF. El estado actual del equipo no cambia y el número no se reutiliza. Esta acción no se puede deshacer.') ?>">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Eliminar acta</button>
                </form>
            <?php endif; ?>
        <?php else: ?>
            <a href="<?= e(url('mantenimientos/' . $id . '/editar')) ?>" class="btn btn-primary"><i class="fa-solid fa-pen me-1"></i>Continuar / cerrar</a>
            <?php if ($puedeAnular): ?>
                <form method="post" action="<?= e(url('mantenimientos/' . $id . '/anular')) ?>"
                      data-confirm="¿Anular este borrador? Se eliminará y el equipo volverá a su estado anterior. Una instalación de Microsoft 365 ya liberada NO se recupera automáticamente.">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="fa-solid fa-trash me-1"></i>Anular borrador</button>
                </form>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php if (!$cerrada): ?>
    <div class="alert alert-warning small"><i class="fa-solid fa-circle-info me-1"></i>El equipo permanece <strong>EN MANTENIMIENTO</strong> hasta que se cierre el acta.</div>
<?php endif; ?>

<?php if ($cerrada): ?>
<div class="row g-3 mb-3">
    <?php if ($mostrarFirma): ?>
    <div class="col-xl-5" id="firma">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3"><i class="fa-solid fa-signature me-1"></i>Firma digital del Jefe de la OTIC</h2>
                <?php if ($firmada): ?>
                    <div class="alert alert-success py-2 mb-2"><i class="fa-solid fa-circle-check me-1"></i>Firma digital verificada.</div>
                    <dl class="row mb-0">
                        <dt class="col-4">Titular</dt><dd class="col-8"><?= e($acta['firma_titular'] ?? '—') ?></dd>
                        <dt class="col-4">DNI</dt><dd class="col-8"><?= e($acta['firma_dni'] ?? '—') ?></dd>
                        <dt class="col-4">Registrada</dt><dd class="col-8"><?= e(fecha((string) $acta['firmado_at'], true)) ?> por <?= e($acta['firmado_por_nombre'] ?? '') ?></dd>
                        <dt class="col-4">SHA-256</dt><dd class="col-8 font-monospace text-break" title="<?= e($acta['firma_sha256']) ?>"><?= e(substr((string) $acta['firma_sha256'], 0, 16)) ?>…</dd>
                    </dl>
                <?php elseif ($puedeFirmar): ?>
                    <ol class="ps-3 mb-3">
                        <li class="mb-1"><a href="<?= e(url('mantenimientos/' . $id . '/descargar')) ?>">Descargue el acta en PDF</a>.</li>
                        <li class="mb-1">Fírmela con <strong>ReFirma PDF</strong> usando su DNIe. Coloque la firma visible en la casilla <strong>VISTO BUENO</strong>.</li>
                        <li>Suba aquí el PDF firmado (sin modificarlo).</li>
                    </ol>
                    <form method="post" action="<?= e(url('mantenimientos/' . $id . '/firmar')) ?>" enctype="multipart/form-data" class="d-flex flex-column gap-2">
                        <?= csrf_field() ?>
                        <input type="file" name="pdf_firmado" class="form-control form-control-sm" accept="application/pdf,.pdf" required>
                        <button type="submit" class="btn btn-success btn-sm align-self-start"><i class="fa-solid fa-upload me-1"></i>Subir PDF firmado</button>
                    </form>
                <?php else: ?>
                    <p class="text-body-secondary mb-0"><i class="fa-regular fa-clock me-1"></i>Pendiente de la firma digital del Jefe de la OTIC.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="<?= $mostrarFirma ? 'col-xl-7' : 'col-12' ?>" id="envio">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">
                    <i class="fa-solid fa-envelope me-1"></i>Envío al usuario
                    <span class="badge <?= e(clase_envio($acta['estado_envio'])) ?> ms-1"><?= e(etiqueta($acta['estado_envio'])) ?></span>
                </h2>
                <?php if (!$correoConfigurado): ?>
                    <div class="alert alert-warning py-2 mb-2"><i class="fa-solid fa-triangle-exclamation me-1"></i>El correo saliente no está configurado (variables <code>MAIL_*</code> del archivo <code>.env</code>).</div>
                <?php elseif (!$puedeEnviar): ?>
                    <p class="text-body-secondary mb-2"><i class="fa-regular fa-clock me-1"></i>Podrá enviarse cuando el acta esté firmada digitalmente.</p>
                <?php else: ?>
                    <p class="text-body-secondary mb-2">
                        El destinatario recibe el PDF y un enlace para <strong>confirmar la recepción</strong>; al confirmarlo, el acta pasa a <strong>Recibido</strong>.
                    </p>
                    <?php if ($correoJefa !== null): ?>
                        <div class="alert alert-info py-2 mb-2"><i class="fa-solid fa-copy me-1"></i>Copia a la Jefa de la OTIC: <strong><?= e($correoJefa) ?></strong></div>
                    <?php else: ?>
                        <div class="alert alert-warning py-2 mb-2">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>No hay correo de la Jefa de la OTIC: el acta se enviará sin copia.
                            <?php if (\App\Core\Auth::esAdministrador()): ?>Configúrelo en <a href="<?= e(url('parametros')) ?>">Parámetros</a> (<code>jefe_otic_email</code>).<?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(url('mantenimientos/' . $id . '/enviar')) ?>" class="row g-2 align-items-start mb-2"
                          <?= $envios !== [] ? 'data-confirm="El acta ya se envió antes. ¿Enviarla de nuevo?"' : '' ?>>
                        <?= csrf_field() ?>
                        <div class="col-sm-8">
                            <input type="email" name="destinatario" maxlength="150" required placeholder="correo@caen.edu.pe"
                                   class="form-control form-control-sm <?= error('destinatario') !== null ? 'is-invalid' : '' ?>"
                                   value="<?= e(old('destinatario', $acta['personal_email'] ?? '')) ?>" aria-label="Correo del destinatario">
                            <div class="invalid-feedback"><?= e(error('destinatario')) ?></div>
                            <?php if (($acta['personal_email'] ?? null) === null): ?>
                                <div class="form-text">El usuario no tiene correo registrado en Personal.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-4 d-grid">
                            <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-paper-plane me-1"></i><?= $envios === [] ? 'Enviar acta' : 'Reenviar' ?></button>
                        </div>
                    </form>
                <?php endif; ?>

                <?php if ($envios !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Enviado</th><th>Destinatario</th><th>Estado</th><th>Recibido</th></tr></thead>
                            <tbody>
                            <?php foreach ($envios as $en): ?>
                                <tr>
                                    <td class="text-nowrap"><?= e(fecha((string) $en['enviado_at'], true)) ?><div class="text-body-secondary"><?= e($en['enviado_por_nombre']) ?></div></td>
                                    <td class="text-break">
                                        <?= e($en['destinatario']) ?><?= (int) $en['adjunto_firmado'] === 1 ? ' <i class="fa-solid fa-signature text-success" title="Con PDF firmado"></i>' : '' ?>
                                        <?php if ($en['copia'] !== null): ?><div class="text-body-secondary">CC: <?= e($en['copia']) ?></div><?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= e(clase_envio($en['estado'])) ?>"><?= e(etiqueta($en['estado'])) ?></span>
                                        <?php if ($en['estado'] === 'ERROR'): ?><div class="text-danger"><?= e($en['error']) ?></div><?php endif; ?>
                                        <?php if ($en['estado'] === 'ENVIADO' && $en['visto_at'] !== null): ?><div class="text-body-secondary" title="Apertura del enlace (puede ser automática)">Abierto <?= e(fecha((string) $en['visto_at'], true)) ?></div><?php endif; ?>
                                    </td>
                                    <td class="text-nowrap"><?= $en['recibido_at'] !== null ? e(fecha((string) $en['recibido_at'], true)) : '<span class="text-body-secondary">—</span>' ?></td>
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
<?php endif; ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Usuario y equipo</h2>
                <dl class="row mb-0">
                    <dt class="col-4">Usuario</dt><dd class="col-8"><?= e($usuario) ?><?= $acta['personal_cargo'] !== null ? ' · ' . e($acta['personal_cargo']) : '' ?></dd>
                    <dt class="col-4">Oficina</dt><dd class="col-8"><?= e($acta['oficina_nombre']) ?></dd>
                    <dt class="col-4">Equipo</dt><dd class="col-8"><a href="<?= e(url('equipos/' . $acta['equipo_id'])) ?>"><?= e(etiqueta($acta['equipo_tipo']) . ' ' . $acta['marca'] . ' ' . $acta['modelo']) ?></a></dd>
                    <dt class="col-4">Serie / Patrim.</dt><dd class="col-8"><?= e(($acta['nro_serie'] ?? '—') . ' / ' . ($acta['codigo_patrimonial'] ?? '—')) ?></dd>
                    <dt class="col-4">Hostname / IP</dt><dd class="col-8"><?= e(($acta['hostname'] ?? '—') . ' / ' . ($acta['ip_lan'] ?? '—')) ?></dd>
                </dl>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Servicio</h2>
                <dl class="row mb-0">
                    <dt class="col-4">Técnico</dt><dd class="col-8"><?= e($acta['tecnico_nombres'] . ' ' . $acta['tecnico_apellidos']) ?></dd>
                    <dt class="col-4">Ingreso</dt><dd class="col-8"><?= e(fecha((string) $acta['fecha_ingreso'], true)) ?></dd>
                    <dt class="col-4">Salida</dt><dd class="col-8"><?= e($acta['fecha_salida'] !== null ? fecha((string) $acta['fecha_salida'], true) : '—') ?></dd>
                    <?php if ($cerrada): ?>
                        <dt class="col-4">Estado final</dt><dd class="col-8"><span class="badge <?= e(clase_estado($acta['estado_equipo_final'])) ?>"><?= e(etiqueta($acta['estado_equipo_final'])) ?></span> <span class="badge <?= e(clase_condicion($acta['condicion_final'])) ?>"><?= e(etiqueta($acta['condicion_final'])) ?></span></dd>
                        <dt class="col-4">Cerrada</dt><dd class="col-8"><?= e(fecha((string) $acta['cerrado_at'], true)) ?> por <?= e($acta['cerrado_por_nombre'] ?? '') ?></dd>
                    <?php endif; ?>
                    <dt class="col-4">Problema</dt><dd class="col-8" style="white-space: pre-line;"><?= e($acta['problema_reportado'] ?? '—') ?></dd>
                </dl>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Actividades realizadas</h2>
                <div class="row g-3">
                    <?php foreach ($checklist as $categoria => $items): ?>
                        <div class="col-md-4">
                            <div class="fw-semibold mb-1"><?= e($nombresCat[$categoria]) ?></div>
                            <?php if ($items === []): ?><div class="text-body-secondary">—</div><?php endif; ?>
                            <?php foreach ($items as $i): ?>
                                <div class="<?= (int) $i['realizado'] === 1 ? '' : 'text-body-secondary' ?>">
                                    <i class="fa-regular <?= (int) $i['realizado'] === 1 ? 'fa-square-check text-success' : 'fa-square' ?> me-1"></i><?= e($i['descripcion']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Componentes</h2>
                <?php if ($componentesUsados === []): ?>
                    <p class="text-body-secondary mb-0">Sin reemplazos ni instalaciones.</p>
                <?php else: ?>
                    <table class="table table-sm mb-0">
                        <thead class="table-light"><tr><th>Componente</th><th>Acción</th><th>Detalle</th></tr></thead>
                        <tbody>
                        <?php foreach ($componentesUsados as $nombre => $c): ?>
                            <tr><td><?= e($nombre) ?></td><td><?= e(etiqueta($c['accion'])) ?></td><td><?= e($c['detalle'] ?? '') ?></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body small">
                <h2 class="h6 text-uppercase text-body-secondary mb-3">Software instalado</h2>
                <?php if ($software === []): ?>
                    <p class="text-body-secondary mb-0">No se registró software.</p>
                <?php else: ?>
                    <?php foreach ($software as $s): ?>
                        <span class="badge text-bg-light border me-1 mb-1"><?= e($s['nombre']) ?><?= $s['version'] !== null ? ' ' . e($s['version']) : '' ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body small">
                <div class="row g-3">
                    <div class="col-md-6">
                        <h2 class="h6 text-uppercase text-body-secondary mb-2">Observaciones</h2>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($acta['observaciones'] ?? '—') ?></p>
                    </div>
                    <div class="col-md-6">
                        <h2 class="h6 text-uppercase text-body-secondary mb-2">Recomendaciones</h2>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($acta['recomendaciones'] ?? '—') ?></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
