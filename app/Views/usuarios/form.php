<?php

declare(strict_types=1);

/**
 * Formulario compartido de alta y edición de usuarios del sistema.
 *
 * @var array<string, mixed>|null $usuario  null = alta
 * @var list<string> $roles
 * @var bool|null $esPropio
 */
$esNuevo = $usuario === null;
$esPropio ??= false;
$valor = static fn (string $campo): string => old($campo, $usuario[$campo] ?? '');
$clase = static fn (string $campo): string => error($campo) !== null ? 'form-control is-invalid' : 'form-control';
$accion = $esNuevo ? url('usuarios') : url('usuarios/' . $usuario['id']);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0">
        <i class="fa-solid <?= $esNuevo ? 'fa-user-plus' : 'fa-user-pen' ?> me-2 text-primary"></i><?= e($esNuevo ? 'Nuevo usuario' : 'Editar usuario') ?>
    </h1>
    <a href="<?= e(url('usuarios')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="<?= e($accion) ?>" novalidate autocomplete="off">
            <?= csrf_field() ?>

            <h2 class="h6 text-uppercase text-body-secondary mb-3">Datos de acceso</h2>
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label for="usuario" class="form-label">Usuario <span class="text-danger">*</span></label>
                    <input type="text" id="usuario" name="usuario" class="<?= e($clase('usuario')) ?>" maxlength="50"
                           value="<?= e($valor('usuario')) ?>" required pattern="[A-Za-z0-9._\-]{3,50}">
                    <div class="invalid-feedback"><?= e(error('usuario')) ?></div>
                    <div class="form-text">Letras, números, punto, guion o guion bajo.</div>
                </div>
                <div class="col-md-4">
                    <label for="rol" class="form-label">Rol <span class="text-danger">*</span></label>
                    <select id="rol" name="rol" class="form-select <?= error('rol') !== null ? 'is-invalid' : '' ?>" required <?= $esPropio ? 'disabled' : '' ?>>
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= e($r) ?>" <?= $valor('rol') === $r || ($valor('rol') === '' && $r === 'TECNICO') ? 'selected' : '' ?>><?= e($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if ($esPropio): ?>
                        <input type="hidden" name="rol" value="<?= e($usuario['rol']) ?>">
                        <div class="form-text">No puede cambiar su propio rol.</div>
                    <?php endif; ?>
                    <div class="invalid-feedback"><?= e(error('rol')) ?></div>
                </div>
                <div class="col-md-4 d-flex align-items-end">
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" id="puede_firmar" name="puede_firmar" value="1"
                               <?= (hay_old() ? old('puede_firmar') === '1' : (int) ($usuario['puede_firmar'] ?? 0) === 1) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="puede_firmar">Puede firmar actas con su DNIe (Jefe de la OTIC)</label>
                        <div class="form-text">Requiere el DNI: se compara con el certificado de la firma.</div>
                    </div>
                </div>
            </div>

            <?php if ($esNuevo): ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <label for="password" class="form-label">Contraseña <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input type="password" id="password" name="password" class="<?= e($clase('password')) ?>" maxlength="72" required autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Mostrar u ocultar"><i class="fa-solid fa-eye"></i></button>
                            <div class="invalid-feedback"><?= e(error('password')) ?></div>
                        </div>
                        <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula y número.</div>
                    </div>
                    <div class="col-md-4">
                        <label for="password_confirmacion" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmacion" name="password_confirmacion" class="<?= e($clase('password_confirmacion')) ?>" maxlength="72" required autocomplete="new-password">
                        <div class="invalid-feedback"><?= e(error('password_confirmacion')) ?></div>
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="debe_cambiar_password" name="debe_cambiar_password" value="1"
                                   <?= old('debe_cambiar_password', '1') === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="debe_cambiar_password">Exigir cambio de contraseña en el primer inicio de sesión</label>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <h2 class="h6 text-uppercase text-body-secondary mb-3">Datos personales</h2>
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
                <div class="col-md-4">
                    <label for="cargo" class="form-label">Cargo</label>
                    <input type="text" id="cargo" name="cargo" class="<?= e($clase('cargo')) ?>" maxlength="150" value="<?= e($valor('cargo')) ?>">
                    <div class="invalid-feedback"><?= e(error('cargo')) ?></div>
                </div>
                <div class="col-md-5">
                    <label for="email" class="form-label">Correo electrónico</label>
                    <input type="email" id="email" name="email" class="<?= e($clase('email')) ?>" maxlength="150" value="<?= e($valor('email')) ?>">
                    <div class="invalid-feedback"><?= e(error('email')) ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= e(url('usuarios')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>
