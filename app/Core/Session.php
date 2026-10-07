<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Envoltura de la sesión PHP: cookies seguras, expiración por inactividad y mensajes flash.
 *
 * Los datos flash se escriben en '_flash_next' y pasan a '_flash' en la siguiente
 * petición, donde pueden leerse; luego se descartan.
 */
final class Session
{
    private static bool $expiroPorInactividad = false;

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $path = parse_url((string) config('app.url'), PHP_URL_PATH);
        $cookiePath = is_string($path) && $path !== '' ? dirname(rtrim($path, '/')) : '/';
        $cookiePath = str_replace('\\', '/', $cookiePath);
        if ($cookiePath === '.' || $cookiePath === '') {
            $cookiePath = '/';
        }

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', (string) config('session.inactividad_segs'));

        session_name((string) config('session.name'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => $cookiePath,
            'secure'   => self::esHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        self::controlarInactividad();
        self::rotarFlash();
    }

    public static function get(string $clave, mixed $defecto = null): mixed
    {
        return $_SESSION[$clave] ?? $defecto;
    }

    public static function set(string $clave, mixed $valor): void
    {
        $_SESSION[$clave] = $valor;
    }

    public static function has(string $clave): bool
    {
        return array_key_exists($clave, $_SESSION);
    }

    public static function remove(string $clave): void
    {
        unset($_SESSION[$clave]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
            session_destroy();
        }
    }

    /** Inicia una sesión nueva y limpia (tras logout o expiración). */
    public static function reiniciar(): void
    {
        self::destroy();
        session_start();
        session_regenerate_id(true);
        $_SESSION['_ultima_actividad'] = time();
    }

    public static function flash(string $clave, mixed $valor): void
    {
        $_SESSION['_flash_next'][$clave] = $valor;
    }

    public static function getFlashData(string $clave, mixed $defecto = null): mixed
    {
        return $_SESSION['_flash'][$clave] ?? $defecto;
    }

    /** Conserva los flash actuales una petición más (p. ej. tras un redirect interno). */
    public static function reflash(): void
    {
        $_SESSION['_flash_next'] = array_merge($_SESSION['_flash'] ?? [], $_SESSION['_flash_next'] ?? []);
    }

    public static function expiroPorInactividad(): bool
    {
        return self::$expiroPorInactividad;
    }

    private static function controlarInactividad(): void
    {
        $limite = (int) config('session.inactividad_segs');
        $ultima = $_SESSION['_ultima_actividad'] ?? null;

        if (is_int($ultima) && isset($_SESSION['auth_id']) && (time() - $ultima) > $limite) {
            self::reiniciar();
            self::$expiroPorInactividad = true;
            self::flash('warning', 'Su sesión expiró por inactividad. Inicie sesión nuevamente.');
        }

        $_SESSION['_ultima_actividad'] = time();
    }

    private static function rotarFlash(): void
    {
        $_SESSION['_flash'] = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_flash_next'] = [];
    }

    private static function esHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (int) ($_SERVER['SERVER_PORT'] ?? 80) === 443;
    }
}
