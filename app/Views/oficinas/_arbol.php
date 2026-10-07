<?php

declare(strict_types=1);

use App\Core\View;

/**
 * Nodo(s) del árbol de oficinas (recursivo).
 *
 * @var list<array<string, mixed>> $nodos
 * @var bool $esAdmin
 */
$iconos = ['DIRECCION' => 'fa-building-columns', 'DEPARTAMENTO' => 'fa-building', 'OFICINA' => 'fa-door-open'];
?>
<ul class="oficina-arbol">
    <?php foreach ($nodos as $nodo):
        $activa = (int) $nodo['activo'] === 1;
        ?>
        <li>
            <div class="oficina-nodo <?= $activa ? '' : 'inactiva' ?>">
                <div class="d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                    <i class="fa-solid <?= e($iconos[$nodo['tipo']] ?? 'fa-door-open') ?> text-primary fa-fw"></i>
                    <div class="text-truncate">
                        <span class="fw-semibold"><?= e($nodo['nombre']) ?></span>
                        <?php if ($nodo['siglas'] !== null): ?><span class="text-body-secondary small">(<?= e($nodo['siglas']) ?>)</span><?php endif; ?>
                        <span class="badge text-bg-light border ms-1"><?= e($nodo['tipo']) ?></span>
                        <?php if (!$activa): ?><span class="badge text-bg-secondary ms-1">Inactiva</span><?php endif; ?>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-3 small text-body-secondary flex-shrink-0">
                    <a href="<?= e(url('personal', ['oficina_id' => $nodo['id']])) ?>" class="text-decoration-none text-body-secondary" title="Personal activo">
                        <i class="fa-solid fa-user me-1"></i><?= e($nodo['personal_activos']) ?>
                    </a>
                    <span title="Equipos (no dados de baja)"><i class="fa-solid fa-desktop me-1"></i><?= e($nodo['equipos_activos']) ?></span>
                    <span class="btn-group btn-group-sm">
                        <?php if ($activa): ?>
                            <a href="<?= e(url('oficinas/crear', ['padre_id' => $nodo['id']])) ?>" class="btn btn-outline-secondary" title="Agregar dependencia"><i class="fa-solid fa-plus"></i></a>
                        <?php endif; ?>
                        <a href="<?= e(url('oficinas/' . $nodo['id'] . '/editar')) ?>" class="btn btn-outline-primary" title="Editar"><i class="fa-solid fa-pen"></i></a>
                    </span>
                    <?php if ($esAdmin): ?>
                        <form method="post" action="<?= e(url('oficinas/' . $nodo['id'] . '/activo')) ?>" class="d-inline"
                              data-confirm="<?= e($activa ? '¿Desactivar la oficina ' . $nodo['nombre'] . '?' : '¿Activar la oficina ' . $nodo['nombre'] . '?') ?>">
                            <?= csrf_field() ?>
                            <?php if ($activa): ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Desactivar"><i class="fa-solid fa-ban"></i></button>
                            <?php else: ?>
                                <button type="submit" class="btn btn-sm btn-outline-success" title="Activar"><i class="fa-solid fa-rotate-left"></i></button>
                            <?php endif; ?>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($nodo['hijos'] !== []): ?>
                <?= View::partial('oficinas/_arbol', ['nodos' => $nodo['hijos'], 'esAdmin' => $esAdmin]) ?>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
