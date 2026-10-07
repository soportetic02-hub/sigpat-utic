<?php

declare(strict_types=1);

/**
 * Paginación reutilizable.
 *
 * @var array{total: int, page: int, per_page: int, last_page: int} $pagina
 * @var string $ruta   Ruta del listado (p. ej. 'usuarios')
 * @var array<string, scalar|null> $filtros  Filtros actuales que se conservan en los enlaces
 */
$filtros ??= [];
$actual = (int) $pagina['page'];
$ultima = (int) $pagina['last_page'];
$desde = $pagina['total'] === 0 ? 0 : (($actual - 1) * $pagina['per_page']) + 1;
$hasta = min($pagina['total'], $actual * $pagina['per_page']);
$inicio = max(1, $actual - 2);
$fin = min($ultima, $actual + 2);
?>
<div class="d-flex flex-column flex-md-row align-items-center justify-content-between gap-2 mt-3">
    <div class="small text-body-secondary">
        Mostrando <?= e($desde) ?>–<?= e($hasta) ?> de <?= e($pagina['total']) ?> registro(s)
    </div>
    <?php if ($ultima > 1): ?>
        <nav aria-label="Paginación">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item <?= $actual <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(url($ruta, array_merge($filtros, ['page' => $actual - 1]))) ?>" aria-label="Anterior">&laquo;</a>
                </li>
                <?php if ($inicio > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= e(url($ruta, array_merge($filtros, ['page' => 1]))) ?>">1</a></li>
                    <?php if ($inicio > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $inicio; $i <= $fin; $i++): ?>
                    <li class="page-item <?= $i === $actual ? 'active' : '' ?>">
                        <a class="page-link" href="<?= e(url($ruta, array_merge($filtros, ['page' => $i]))) ?>"><?= e($i) ?></a>
                    </li>
                <?php endfor; ?>
                <?php if ($fin < $ultima): ?>
                    <?php if ($fin < $ultima - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= e(url($ruta, array_merge($filtros, ['page' => $ultima]))) ?>"><?= e($ultima) ?></a></li>
                <?php endif; ?>
                <li class="page-item <?= $actual >= $ultima ? 'disabled' : '' ?>">
                    <a class="page-link" href="<?= e(url($ruta, array_merge($filtros, ['page' => $actual + 1]))) ?>" aria-label="Siguiente">&raquo;</a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>
</div>
