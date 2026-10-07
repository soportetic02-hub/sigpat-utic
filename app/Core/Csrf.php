<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Token CSRF por sesión. Se envía en formularios como campo "_csrf"
 * o en peticiones AJAX con la cabecera "X-CSRF-TOKEN".
 */
final class Csrf
{
    private const CLAVE = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::CLAVE);
        if (!is_string($token) || strlen($token) !== 64) {
            $token = bin2hex(random_bytes(32));
            Session::set(self::CLAVE, $token);
        }

        return $token;
    }

    public static function validar(?string $token): bool
    {
        $esperado = Session::get(self::CLAVE);

        return is_string($esperado) && is_string($token) && $token !== '' && hash_equals($esperado, $token);
    }

    /** Genera un token nuevo (tras iniciar o cerrar sesión). */
    public static function regenerar(): void
    {
        Session::set(self::CLAVE, bin2hex(random_bytes(32)));
    }
}
