<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Exige sesión iniciada. Si el usuario debe cambiar su contraseña,
 * solo le permite acceder a la pantalla de cambio y a cerrar sesión.
 */
final class AuthMiddleware implements Middleware
{
    private const RUTAS_PERMITIDAS_CAMBIO_PASSWORD = ['/perfil/password', '/logout'];

    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            if ($request->isAjax()) {
                throw new HttpException(401, 'Su sesión expiró. Inicie sesión nuevamente.');
            }
            if ($request->method() === 'GET') {
                Session::set('url_intentada', $request->path());
            }
            if (!Session::expiroPorInactividad()) {
                Session::flash('warning', 'Debe iniciar sesión para continuar.');
            }
            Response::redirect('login');
        }

        if (Auth::debeCambiarPassword() && !in_array($request->path(), self::RUTAS_PERMITIDAS_CAMBIO_PASSWORD, true)) {
            if ($request->isAjax()) {
                throw new HttpException(403, 'Debe cambiar su contraseña antes de continuar.');
            }
            Session::flash('warning', 'Por seguridad, debe cambiar su contraseña antes de continuar.');
            Response::redirect('perfil/password');
        }
    }
}
