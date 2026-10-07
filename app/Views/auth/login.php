<?php declare(strict_types=1); ?>
<div class="card auth-card shadow-lg border-0">
    <div class="card-body p-4 p-md-5">
        <div class="text-center mb-4">
            <div class="auth-logo mb-3"><i class="fa-solid fa-shield-halved"></i></div>
            <h1 class="h4 fw-bold mb-1">SIGPAT-OTIC</h1>
            <p class="text-body-secondary small mb-0">Gestión patrimonial, licencias y mantenimiento</p>
        </div>

        <?= \App\Core\View::partial('partials/flash') ?>

        <form method="post" action="<?= e(url('login')) ?>" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="usuario" class="form-label">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-user"></i></span>
                    <input type="text" class="form-control" id="usuario" name="usuario" maxlength="50"
                           value="<?= e(old('usuario')) ?>" required autofocus autocomplete="username">
                </div>
            </div>
            <div class="mb-4">
                <label for="password" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fa-solid fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" maxlength="72"
                           required autocomplete="current-password">
                    <button class="btn btn-outline-secondary" type="button" data-toggle-password="#password"
                            aria-label="Mostrar u ocultar contraseña">
                        <i class="fa-solid fa-eye"></i>
                    </button>
                </div>
            </div>
            <button type="submit" class="btn btn-primary w-100 py-2">
                <i class="fa-solid fa-right-to-bracket me-2"></i>Ingresar
            </button>
        </form>
    </div>
</div>
