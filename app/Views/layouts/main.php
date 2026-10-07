<?php

declare(strict_types=1);

use App\Core\View;

/** @var string $contenido */
/** @var string|null $titulo */
$usuarioActual = auth_user();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-url" content="<?= e(url()) ?>">
    <title><?= e(($titulo ?? '') !== '' ? $titulo . ' · ' : '') ?>SIGPAT-OTIC</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="app-body">
    <?= View::partial('partials/sidebar', ['usuarioActual' => $usuarioActual]) ?>

    <div class="app-main">
        <?= View::partial('partials/navbar', ['usuarioActual' => $usuarioActual, 'titulo' => $titulo ?? '']) ?>

        <main class="app-content container-fluid">
            <?= View::partial('partials/flash') ?>
            <?= $contenido ?>
        </main>

        <footer class="app-footer text-body-secondary small">
            SIGPAT-OTIC &middot; Oficina de Tecnologías de la Información y Comunicación &middot; CAEN-EPG
        </footer>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
