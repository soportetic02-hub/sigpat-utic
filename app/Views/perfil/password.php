<?php

declare(strict_types=1);

/** @var bool $obligatorio */
?>
<h1 class="h4 mb-3"><i class="fa-solid fa-key me-2 text-primary"></i>Cambiar contraseña</h1>

<div class="row">
    <div class="col-lg-6">
        <?php if ($obligatorio): ?>
            <div class="alert alert-warning d-flex gap-2">
                <i class="fa-solid fa-triangle-exclamation mt-1"></i>
                <div>Su contraseña es temporal. Debe definir una contraseña personal para continuar usando el sistema.</div>
            </div>
        <?php endif; ?>
        <div class="card shadow-sm border-0">
            <div class="card-body">
                <form method="post" action="<?= e(url('perfil/password')) ?>" novalidate autocomplete="off">
                    <?= csrf_field() ?>
                    <div class="mb-3">
                        <label for="password_actual" class="form-label">Contraseña actual <span class="text-danger">*</span></label>
                        <input type="password" id="password_actual" name="password_actual" maxlength="72" required autocomplete="current-password"
                               class="form-control <?= error('password_actual') !== null ? 'is-invalid' : '' ?>">
                        <div class="invalid-feedback"><?= e(error('password_actual')) ?></div>
                    </div>
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
                    <div class="mb-4">
                        <label for="password_confirmacion" class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                        <input type="password" id="password_confirmacion" name="password_confirmacion" maxlength="72" required autocomplete="new-password"
                               class="form-control <?= error('password_confirmacion') !== null ? 'is-invalid' : '' ?>">
                        <div class="invalid-feedback"><?= e(error('password_confirmacion')) ?></div>
                    </div>
                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Actualizar contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
