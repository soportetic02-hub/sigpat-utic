<?php

declare(strict_types=1);

/**
 * Alta y edición de personal. En edición, si la persona tiene equipos y se
 * cambia su oficina, se abre el modal de rotación antes de guardar.
 *
 * @var array<string, mixed>|null $persona  null = alta
 * @var int|null $oficinaId
 * @var list<array{id: int, etiqueta: string, nivel: int}> $oficinas
 * @var list<array<string, mixed>> $equipos  equipos a cargo (solo en edición)
 */
$esNueva = $persona === null;
$valor = static fn (string $campo): string => old($campo, $persona[$campo] ?? '');
$clase = static fn (string $campo): string => error($campo) !== null ? 'form-control is-invalid' : 'form-control';
$oficinaSeleccionada = old('oficina_id', $oficinaId === null ? '' : (string) $oficinaId);
$accion = $esNueva ? url('personal') : url('personal/' . $persona['id']);
$volver = $esNueva ? url('personal') : url('personal/' . $persona['id']);
$hayRotacion = !$esNueva && $equipos !== [];
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-id-card me-2 text-primary"></i><?= e($esNueva ? 'Registrar personal' : 'Editar personal') ?></h1>
    <a href="<?= e($volver) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="<?= e($accion) ?>" novalidate id="form-personal"
              data-oficina-original="<?= e($esNueva ? '' : (string) $persona['oficina_id']) ?>"
              data-rotacion="<?= $hayRotacion ? '1' : '0' ?>"
              data-rotacion-error="<?= error('rotacion') !== null ? '1' : '0' ?>">
            <?= csrf_field() ?>

            <div class="row g-3">
                <div class="col-md-6">
                    <label for="nombres" class="form-label">Nombres <span class="text-danger">*</span></label>
                    <input type="text" id="nombres" name="nombres" class="<?= e($clase('nombres')) ?>" maxlength="100" value="<?= e($valor('nombres')) ?>" required>
                    <div class="invalid-feedback"><?= e(error('nombres')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="apellidos" class="form-label">Apellidos <span class="text-danger">*</span></label>
                    <input type="text" id="apellidos" name="apellidos" class="<?= e($clase('apellidos')) ?>" maxlength="100" value="<?= e($valor('apellidos')) ?>" required>
                    <div class="invalid-feedback"><?= e(error('apellidos')) ?></div>
                </div>
                <div class="col-md-3">
                    <label for="dni" class="form-label">DNI</label>
                    <input type="text" id="dni" name="dni" class="<?= e($clase('dni')) ?>" maxlength="8" inputmode="numeric" pattern="[0-9]{8}" value="<?= e($valor('dni')) ?>">
                    <div class="invalid-feedback"><?= e(error('dni')) ?></div>
                </div>
                <div class="col-md-5">
                    <label for="cargo" class="form-label">Cargo</label>
                    <input type="text" id="cargo" name="cargo" class="<?= e($clase('cargo')) ?>" maxlength="150" value="<?= e($valor('cargo')) ?>">
                    <div class="invalid-feedback"><?= e(error('cargo')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono / anexo</label>
                    <input type="text" id="telefono" name="telefono" class="<?= e($clase('telefono')) ?>" maxlength="30" value="<?= e($valor('telefono')) ?>">
                    <div class="invalid-feedback"><?= e(error('telefono')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" id="email" name="email" class="<?= e($clase('email')) ?>" maxlength="150" value="<?= e($valor('email')) ?>">
                    <div class="invalid-feedback"><?= e(error('email')) ?></div>
                </div>
                <div class="col-md-6">
                    <label for="oficina_id" class="form-label">Oficina <span class="text-danger">*</span></label>
                    <select id="oficina_id" name="oficina_id" class="form-select <?= error('oficina_id') !== null ? 'is-invalid' : '' ?>" required>
                        <option value="">— Seleccione —</option>
                        <?php foreach ($oficinas as $op): ?>
                            <option value="<?= e($op['id']) ?>" <?= $oficinaSeleccionada === (string) $op['id'] ? 'selected' : '' ?>>
                                <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('oficina_id')) ?></div>
                    <?php if ($hayRotacion): ?>
                        <div class="form-text"><i class="fa-solid fa-circle-info me-1"></i>Tiene <?= e(count($equipos)) ?> equipo(s) a su cargo: si cambia la oficina se le preguntará qué hacer con cada uno.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($hayRotacion): ?>
                <div class="modal fade" id="modal-rotacion" tabindex="-1" aria-labelledby="modal-rotacion-titulo" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h2 class="modal-title h5" id="modal-rotacion-titulo"><i class="fa-solid fa-people-arrows me-2"></i>Rotación de personal</h2>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <p>
                                    <strong><?= e($persona['nombres'] . ' ' . $persona['apellidos']) ?></strong> pasará a
                                    <strong id="rotacion-destino">otra oficina</strong>. Indique qué hacer con cada equipo a su cargo:
                                </p>
                                <?php if (error('rotacion') !== null): ?>
                                    <div class="alert alert-danger small"><?= e(error('rotacion')) ?></div>
                                <?php endif; ?>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Equipo</th>
                                                <th>Ubicación actual</th>
                                                <th>Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach ($equipos as $eq): ?>
                                            <tr>
                                                <td>
                                                    <span class="fw-semibold"><?= e($eq['tipo'] . ' ' . $eq['marca'] . ' ' . $eq['modelo']) ?></span>
                                                    <div class="small text-body-secondary">
                                                        Serie: <?= e($eq['nro_serie'] ?? '—') ?> · Patrimonial: <?= e($eq['codigo_patrimonial'] ?? '—') ?>
                                                    </div>
                                                </td>
                                                <td class="small"><?= e($eq['oficina_siglas'] ?? $eq['oficina_nombre']) ?></td>
                                                <td>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="equipos_accion[<?= e($eq['id']) ?>]"
                                                               id="rot-t-<?= e($eq['id']) ?>" value="TRASLADAR" checked>
                                                        <label class="form-check-label small" for="rot-t-<?= e($eq['id']) ?>">Trasladar con la persona</label>
                                                    </div>
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="radio" name="equipos_accion[<?= e($eq['id']) ?>]"
                                                               id="rot-d-<?= e($eq['id']) ?>" value="DEJAR">
                                                        <label class="form-check-label small" for="rot-d-<?= e($eq['id']) ?>">
                                                            Dejar en <?= e($eq['oficina_siglas'] ?? $eq['oficina_nombre']) ?> sin responsable
                                                        </label>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <p class="small text-body-secondary mb-0">
                                    Todos los cambios se registran en el historial de asignaciones y se guardan juntos: si algo falla, no se aplica ninguno.
                                </p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="button" class="btn btn-primary" id="btn-confirmar-rotacion">
                                    <i class="fa-solid fa-check me-1"></i>Confirmar y guardar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= e($volver) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php if ($hayRotacion): ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-personal');
    var selectOficina = document.getElementById('oficina_id');
    var modalEl = document.getElementById('modal-rotacion');
    var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    var confirmado = false;

    function actualizarDestino() {
        var opcion = selectOficina.options[selectOficina.selectedIndex];
        document.getElementById('rotacion-destino').textContent = opcion ? opcion.text.trim() : 'otra oficina';
    }

    form.addEventListener('submit', function (evento) {
        var cambia = selectOficina.value !== '' && selectOficina.value !== form.dataset.oficinaOriginal;
        if (cambia && !confirmado) {
            evento.preventDefault();
            actualizarDestino();
            modal.show();
        }
    });

    document.getElementById('btn-confirmar-rotacion').addEventListener('click', function () {
        confirmado = true;
        modal.hide();
        form.requestSubmit();
    });

    if (form.dataset.rotacionError === '1') {
        actualizarDestino();
        modal.show();
    }
});
</script>
<?php endif; ?>
