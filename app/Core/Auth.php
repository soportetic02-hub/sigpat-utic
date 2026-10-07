<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\UsuarioSistemaModel;

/**
 * Identidad del usuario del sistema autenticado en la sesión.
 * El usuario se relee de la BD en cada petición: si fue desactivado,
 * la sesión deja de ser válida de inmediato.
 */
final class Auth
{
    public const ADMINISTRADOR = 'ADMINISTRADOR';
    public const TECNICO = 'TECNICO';

    /** @var array<string, mixed>|null */
    private static ?array $usuario = null;
    private static bool $cargado = false;

    /** Registra en la sesión al usuario que acaba de autenticarse. */
    public static function login(array $usuario): void
    {
        Session::regenerate();
        Csrf::regenerar();
        Session::set('auth_id', (int) $usuario['id']);
        Session::set('auth_inicio', time());
        self::$usuario = null;
        self::$cargado = false;
    }

    public static function logout(): void
    {
        self::$usuario = null;
        self::$cargado = true;
        Session::reiniciar();
        Csrf::regenerar();
    }

    /** @return array<string, mixed>|null */
    public static function user(): ?array
    {
        if (self::$cargado) {
            return self::$usuario;
        }
        self::$cargado = true;

        $id = Session::get('auth_id');
        if (!is_int($id)) {
            return null;
        }

        $usuario = (new UsuarioSistemaModel())->find($id);
        if ($usuario === null || (int) $usuario['activo'] !== 1) {
            Session::remove('auth_id');

            return null;
        }
        unset($usuario['password_hash']);
        self::$usuario = $usuario;

        return self::$usuario;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        $usuario = self::user();

        return $usuario === null ? null : (int) $usuario['id'];
    }

    public static function rol(): ?string
    {
        return self::user()['rol'] ?? null;
    }

    public static function tieneRol(string ...$roles): bool
    {
        $rol = self::rol();

        return $rol !== null && in_array($rol, $roles, true);
    }

    public static function esAdministrador(): bool
    {
        return self::tieneRol(self::ADMINISTRADOR);
    }

    public static function nombreCompleto(): string
    {
        $u = self::user();

        return $u === null ? '' : trim($u['nombres'] . ' ' . $u['apellidos']);
    }

    public static function debeCambiarPassword(): bool
    {
        return (int) (self::user()['debe_cambiar_password'] ?? 0) === 1;
    }

    /** Fuerza la relectura del usuario (p. ej. tras cambiar su contraseña). */
    public static function refrescar(): void
    {
        self::$cargado = false;
        self::$usuario = null;
    }
}
