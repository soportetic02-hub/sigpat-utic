<?php

declare(strict_types=1);

/**
 * @var array<string, mixed>|null $oficina  null = alta
 * @var int|null $padreId
 * @var list<array{id: int, etiqueta: string, nivel: int}> $opciones
 * @var list<string> $tipos
 */
$esNueva = $oficina === null;
$valor = static fn (string $campo, mixed $defecto = ''): string => old($campo, $oficina[$campo] ?? $defecto);
$clase = static fn (string $campo): string => error($campo) !== null ? 'form-control is-invalid' : 'form-control';
$padreSeleccionado = old('padre_id', $padreId === null ? '' : (string) $padreId);
$accion = $esNueva ? url('oficinas') : url('oficinas/' . $oficina['id']);
?>
<div class="d-flex align-items-center justify-content-between mb-3">
    <h1 class="h4 mb-0"><i class="fa-solid fa-sitemap me-2 text-primary"></i><?= e($esNueva ? 'Nueva oficina' : 'Editar oficina') ?></h1>
    <a href="<?= e(url('oficinas')) ?>" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="post" action="<?= e($accion) ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-md-8">
                    <label for="nombre" class="form-label">Nombre <span class="text-danger">*</span></label>
                    <input type="text" id="nombre" name="nombre" class="<?= e($clase('nombre')) ?>" maxlength="150" value="<?= e($valor('nombre')) ?>" required>
                    <div class="invalid-feedback"><?= e(error('nombre')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="siglas" class="form-label">Siglas</label>
                    <input type="text" id="siglas" name="siglas" class="<?= e($clase('siglas')) ?> text-uppercase" maxlength="20" value="<?= e($valor('siglas')) ?>">
                    <div class="invalid-feedback"><?= e(error('siglas')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                    <select id="tipo" name="tipo" class="form-select <?= error('tipo') !== null ? 'is-invalid' : '' ?>" required>
                        <?php foreach ($tipos as $t): ?>
                            <option value="<?= e($t) ?>" <?= $valor('tipo', 'OFICINA') === $t ? 'selected' : '' ?>><?= e($t) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('tipo')) ?></div>
                </div>
                <div class="col-md-8">
                    <label for="padre_id" class="form-label">Depende de</label>
                    <select id="padre_id" name="padre_id" class="form-select <?= error('padre_id') !== null ? 'is-invalid' : '' ?>">
                        <option value="">— Ninguna (nivel superior) —</option>
                        <?php foreach ($opciones as $op): ?>
                            <option value="<?= e($op['id']) ?>" <?= $padreSeleccionado === (string) $op['id'] ? 'selected' : '' ?>>
                                <?= str_repeat('&nbsp;&nbsp;&nbsp;&nbsp;', $op['nivel']) ?><?= e($op['etiqueta']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="invalid-feedback"><?= e(error('padre_id')) ?></div>
                </div>
                <div class="col-md-8">
                    <label for="ubicacion" class="form-label">Ubicación</label>
                    <input type="text" id="ubicacion" name="ubicacion" class="<?= e($clase('ubicacion')) ?>" maxlength="150"
                           placeholder="Pabellón, piso o ambiente" value="<?= e($valor('ubicacion')) ?>">
                    <div class="invalid-feedback"><?= e(error('ubicacion')) ?></div>
                </div>
                <div class="col-md-4">
                    <label for="telefono" class="form-label">Teléfono / anexo</label>
                    <input type="text" id="telefono" name="telefono" class="<?= e($clase('telefono')) ?>" maxlength="30" value="<?= e($valor('telefono')) ?>">
                    <div class="invalid-feedback"><?= e(error('telefono')) ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <a href="<?= e(url('oficinas')) ?>" class="btn btn-outline-secondary">Cancelar</a>
                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-floppy-disk me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>
