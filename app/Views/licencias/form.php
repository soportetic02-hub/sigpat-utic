<?php

declare(strict_types=1);

use App\Services\LicenciaService;

/**
 * @var array<string, mixed>|null $licencia  null = alta
 * @var list<string> $estados
 */
$esNueva = $licencia === null;
$v = static fn (string $c, string $def = ''): string => old($c, $licencia[$c] ?? $def);
$cls = static fn (string $c, string $base = 'form-control'): string => $base . (error($c) !== null ? ' is-invalid' : '');
$accion = $esNueva ? url('licencias') : url('licencias/' . $licencia['id']);
$volver = $esNueva ? url('licencias') : url('licencias/' . $licencia['id']);
$tieneContrasena = !$esNueva && $licencia['contrasena_cifrada'] !== null;
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-brands fa-microsoft me-2 text-primary"></i><?= e($esNueva ? 'Registrar cuenta Microsoft 365' : 'Editar cuenta Microsoft 365') ?></h1>
    <a href="<?= e($volver) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="<?= e($accion) ?>" novalidate autocomplete="off">
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="codigo" class="form-label">Código <span class="text-danger">*</span></label>
                    <input type="text" id="codigo" name="codigo" class="<?= e($cls('codigo')) ?>" maxlength="20"
                           placeholder="licencia01" value="<?= e($v('codigo')) ?>" required>
                    <div class="invalid-feedback"><?= e(error('codigo')) ?></div>
                </div>
                <div class="col-md-5">
                    <label for="correo" class="form-label">Correo de la cuenta <span class="text-danger">*</span></label>
                    <input type="email" id="correo" name="correo" class="<?= e($cls('correo')) ?>" maxlength="150"
                           placeholder="lic01@caen2023.onmicrosoft.com" value="<?= e($v('correo')) ?>" required>
                    <div class="invalid-feedback"><?= e(error('correo')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="plan" class="form-label">Plan</label>
                    <input type="text" id="plan" name="plan" class="<?= e($cls('plan')) ?>" maxlength="80" list="planes"
                           value="<?= e($v('plan', LicenciaService::PLAN_DEFECTO)) ?>">
                    <datalist id="planes">
                        <option value="Microsoft 365 Empresa Estándar"><option value="Microsoft 365 Empresa Básico"><option value="Microsoft 365 Empresa Premium"><option value="Microsoft 365 Apps para empresas">
                    </datalist>
                    <div class="invalid-feedback"><?= e(error('plan')) ?></div>
                </div>

                <div class="col-md-6">
                    <label for="password_cuenta" class="form-label">Contraseña</label>
                    <div class="input-group has-validation">
                        <input type="password" id="password_cuenta" name="password_cuenta" class="<?= e($cls('password_cuenta')) ?> font-monospace"
                               maxlength="128" autocomplete="new-password"
                               placeholder="<?= e($tieneContrasena ? 'Déjela vacía para conservar la actual' : 'Contraseña de la cuenta') ?>">
                        <button type="button" class="btn btn-outline-secondary" data-toggle-password="#password_cuenta" title="Mostrar u ocultar" aria-label="Mostrar u ocultar la contraseña">
                            <i class="fa-solid fa-eye"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btn-generar" title="Generar una contraseña segura">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generar
                        </button>
                        <div class="invalid-feedback"><?= e(error('password_cuenta')) ?></div>
                    </div>
                    <div class="form-text">
                        Se guarda cifrada. <?= $tieneContrasena ? 'Ya hay una contraseña registrada: escriba una nueva solo si cambió.' : '' ?>
                        Solo un administrador puede verla, y cada vez queda registrado en la auditoría.
                    </div>
                </div>
                <div class="col-md-3">
                    <label for="estado" class="form-label">Estado <span class="text-danger">*</span></label>
                    <select id="estado" name="estado" class="<?= e($cls('estado', 'form-select')) ?>">
                        <?php foreach ($estados as $es): ?>
                            <option value="<?= e($es) ?>" <?= $v('estado', 'ACTIVA') === $es ? 'selected' : '' ?>><?= e(etiqueta($es)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('estado')) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <label for="fecha_alta" class="form-label">Fecha de alta</label>
                    <input type="date" id="fecha_alta" name="fecha_alta" class="<?= e($cls('fecha_alta')) ?>" value="<?= e($v('fecha_alta')) ?>">
                    <div class="invalid-feedback"><?= e(error('fecha_alta')) ?></div>
                </div>
                <div class="col-6 col-md-3">
                    <label for="fecha_vencimiento" class="form-label">Fecha de vencimiento</label>
                    <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" class="<?= e($cls('fecha_vencimiento')) ?>" value="<?= e($v('fecha_vencimiento')) ?>">
                    <div class="invalid-feedback"><?= e(error('fecha_vencimiento')) ?></div>
                </div>
                <div class="col-12">
                    <label for="observaciones" class="form-label">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" class="<?= e($cls('observaciones')) ?>" rows="3" maxlength="2000"><?= e($v('observaciones')) ?></textarea>
                    <div class="invalid-feedback"><?= e(error('observaciones')) ?></div>
                </div>
            </div>
            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= e($volver) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Genera una contraseña de 16 caracteres con el generador criptográfico del navegador.
    document.getElementById('btn-generar').addEventListener('click', function () {
        var grupos = ['ABCDEFGHJKLMNPQRSTUVWXYZ', 'abcdefghijkmnopqrstuvwxyz', '23456789', '.$#@*-_'];
        var todos = grupos.join('');
        var valores = new Uint32Array(16);
        window.crypto.getRandomValues(valores);
        var caracteres = [];
        for (var i = 0; i < valores.length; i++) {
            // Los 4 primeros garantizan una letra mayúscula, una minúscula, un número y un símbolo.
            var fuente = i < grupos.length ? grupos[i] : todos;
            caracteres.push(fuente.charAt(valores[i] % fuente.length));
        }
        // Mezcla (Fisher-Yates) para que los obligatorios no queden siempre al inicio.
        var mezcla = new Uint32Array(caracteres.length);
        window.crypto.getRandomValues(mezcla);
        for (var j = caracteres.length - 1; j > 0; j--) {
            var k = mezcla[j] % (j + 1);
            var tmp = caracteres[j];
            caracteres[j] = caracteres[k];
            caracteres[k] = tmp;
        }
        var input = document.getElementById('password_cuenta');
        input.value = caracteres.join('');
        input.type = 'text';
        var icono = document.querySelector('[data-toggle-password="#password_cuenta"] i');
        if (icono) {
            icono.classList.remove('fa-eye');
            icono.classList.add('fa-eye-slash');
        }
    });
});
</script>
