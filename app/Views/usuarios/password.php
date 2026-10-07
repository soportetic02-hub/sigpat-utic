<?php

declare(strict_types=1);

/** @var array{id: int, usuario: string, nombres: string, apellidos: string} $usuario */
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-key me-2 text-primary"></i>Restablecer contraseña</h1>
    <a href="<?= e(url('usuarios')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <p class="mb-4">
                    Usuario: <strong><?= e($usuario['usuario']) ?></strong>
                    &mdash; <?= e($usuario['nombres'] . ' ' . $usuario['apellidos']) ?>
                </p>
                <form method="post" action="<?= e(url('usuarios/' . $usuario['id'] . '/password')) ?>" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="password" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                        <div class="input-group has-validation">
                            <input type="password" id="password" name="password" maxlength="72" required autocomplete="new-password"
                                   class="form-control <?= error('password') !== null ? 'is-invalid' : '' ?>">
                            <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password" aria-label="Mostrar u ocultar"><i class="fa-solid fa-eye"></i></button>
                            <div class="invalid-feedback"><?= e(error('password')) ?></div>
                        </div>
                        <div class="form-text">Mínimo 8 caracteres, con mayúscula, minúscula y número.</div>
                    </div>
                    <div class="mb-3">
                        <label for="password_confirmacion" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmacion" name="password_confirmacion" maxlength="72" required autocomplete="new-password"
                               class="form-control <?= error('password_confirmacion') !== null ? 'is-invalid' : '' ?>">
                        <div class="invalid-feedback"><?= e(error('password_confirmacion')) ?></div>
                    </div>
                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="debe_cambiar_password" name="debe_cambiar_password" value="1" checked>
                        <label class="form-check-label" for="debe_cambiar_password">Exigir cambio de contraseña en el próximo inicio de sesión</label>
                    </div>
                    <p class="small text-body-secondary">Restablecer la contraseña también desbloquea la cuenta si estaba bloqueada por intentos fallidos.</p>
                    <div class="d-flex justify-content-end gap-2">
                        <a href="<?= e(url('usuarios')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
