<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Valida el token CSRF de toda petición POST (campo _csrf o cabecera X-CSRF-TOKEN).
 */
final class CsrfMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        if ($request->method() !== 'POST') {
            return;
        }

        $token = $request->post()['_csrf'] ?? $request->header('X-CSRF-TOKEN');
        if (Csrf::validar(is_string($token) ? $token : null)) {
            return;
        }

        if ($request->isAjax()) {
            // 403 y no 419: Apache no reconoce 419 y lo convierte en 500.
            throw new HttpException(403, HttpException::mensajePorDefecto(419));
        }

        Session::flash('warning', 'El formulario expiró o no es válido. Vuelva a intentarlo.');
        Response::back($request->path() === '/login' ? 'login' : '/');
    }
}
