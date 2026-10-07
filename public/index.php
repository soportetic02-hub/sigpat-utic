<?php

declare(strict_types=1);

/*
 * Front controller de SIGPAT-OTIC. Único punto de entrada web.
 */

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;

$raiz = dirname(__DIR__);

if (!is_file($raiz . '/vendor/autoload.php')) {
    http_response_code(500);
    echo 'Faltan las dependencias. Ejecute "composer install" en la carpeta del proyecto.';
    exit;
}

require $raiz . '/vendor/autoload.php';

/**
 * Muestra una página de error (o JSON en peticiones AJAX) sin exponer detalles internos.
 */
$responderError = static function (int $status, string $mensaje, ?Throwable $detalle = null): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($status);
    }

    $request = Request::actual();
    if ($request !== null && $request->isAjax()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'mensaje' => $mensaje], JSON_UNESCAPED_UNICODE);

        return;
    }

    $vista = in_array($status, [403, 404], true) ? 'errors/' . $status : 'errors/500';
    try {
        echo View::render($vista, [
            'titulo'  => 'Error ' . $status,
            'status'  => $status,
            'mensaje' => $mensaje,
            'detalle' => $detalle !== null && config('app.debug') === true ? $detalle : null,
        ], 'auth');
    } catch (Throwable $e) {
        Logger::exception($e, 'No se pudo renderizar la página de error');
        echo 'Ocurrió un error inesperado. Intente nuevamente más tarde.';
    }
};

try {
    config('app.url');
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Error de configuración: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

set_error_handler(static function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if ((error_reporting() & $nivel) === 0) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

try {
    $request = new Request();
    Session::start();

    $router = new Router();
    (require $raiz . '/app/Config/routes.php')($router);

    ob_start();
    $router->dispatch($request);
    ob_end_flush();
} catch (HttpException $e) {
    if ($e->getStatus() === 401) {
        if (isset($request) && !$request->isAjax()) {
            Response::redirect('login');
        }
        $responderError(401, $e->getMessage());
    } else {
        if ($e->getStatus() >= 500) {
            Logger::exception($e);
        }
        $responderError($e->getStatus(), $e->getMessage());
    }
} catch (Throwable $e) {
    Logger::exception($e);
    $responderError(500, 'Ocurrió un error inesperado. El incidente fue registrado; intente nuevamente más tarde.', $e);
}
