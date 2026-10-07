<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\ValidationException;
use App\Models\UsuarioSistemaModel;

/**
 * Inicio y cierre de sesión con bloqueo por intentos fallidos (sección 9).
 */
final class AutenticacionService
{
    private const MENSAJE_GENERICO = 'Usuario o contraseña incorrectos.';

    /** Hash ficticio para igualar el tiempo de respuesta cuando el usuario no existe. */
    private const HASH_FICTICIO = '$2y$12$4i5I89d25nUXyw//w.zKEOsRVgz9BNsStbOo/CN19.1i5kwIIA4/W';

    private UsuarioSistemaModel $usuarios;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->usuarios = new UsuarioSistemaModel();
        $this->auditoria = new AuditoriaService();
    }

    /**
     * Valida credenciales e inicia la sesión.
     *
     * @throws ValidationException con un mensaje apto para mostrar al usuario
     */
    public function iniciarSesion(string $usuario, string $password): void
    {
        $usuario = trim($usuario);
        if ($usuario === '' || $password === '') {
            throw ValidationException::campo('usuario', 'Ingrese su usuario y contraseña.');
        }

        $maxIntentos = (int) config('auth.max_intentos');
        $minutosBloqueo = (int) config('auth.bloqueo_minutos');
        $registro = $this->usuarios->findByUsuario($usuario);

        if ($registro === null) {
            password_verify($password, self::HASH_FICTICIO);
            $this->auditoria->registrarSeguro(AuditoriaService::LOGIN_FALLIDO, 'usuarios_sistema', null,
                ['motivo' => 'Usuario inexistente'], null, $usuario);
            throw ValidationException::campo('usuario', self::MENSAJE_GENERICO);
        }

        $id = (int) $registro['id'];

        $minutosRestantes = $this->usuarios->minutosBloqueoRestantes($id);
        if ($minutosRestantes > 0) {
            $this->auditoria->registrarSeguro(AuditoriaService::LOGIN_FALLIDO, 'usuarios_sistema', $id,
                ['motivo' => 'Cuenta bloqueada'], $id, $usuario);
            throw ValidationException::campo('usuario', sprintf(
                'Cuenta bloqueada temporalmente por intentos fallidos. Intente nuevamente en %d minuto(s).',
                $minutosRestantes
            ));
        }

        if (!password_verify($password, (string) $registro['password_hash'])) {
            $intentos = $this->usuarios->registrarIntentoFallido($id, $maxIntentos, $minutosBloqueo);
            $this->auditoria->registrarSeguro(AuditoriaService::LOGIN_FALLIDO, 'usuarios_sistema', $id,
                ['motivo' => 'Contraseña incorrecta', 'intento' => $intentos], $id, $usuario);

            if ($intentos >= $maxIntentos) {
                throw ValidationException::campo('usuario', sprintf(
                    'Demasiados intentos fallidos. La cuenta quedó bloqueada durante %d minutos.',
                    $minutosBloqueo
                ));
            }
            $restantes = $maxIntentos - $intentos;
            throw ValidationException::campo('usuario', self::MENSAJE_GENERICO . sprintf(
                ' Le queda(n) %d intento(s) antes del bloqueo.',
                $restantes
            ));
        }

        if ((int) $registro['activo'] !== 1) {
            $this->auditoria->registrarSeguro(AuditoriaService::LOGIN_FALLIDO, 'usuarios_sistema', $id,
                ['motivo' => 'Usuario desactivado'], $id, $usuario);
            throw ValidationException::campo('usuario', 'Su cuenta está desactivada. Comuníquese con el administrador.');
        }

        if (password_needs_rehash((string) $registro['password_hash'], PASSWORD_BCRYPT)) {
            $this->usuarios->update($id, ['password_hash' => password_hash($password, PASSWORD_BCRYPT)]);
        }

        $this->usuarios->registrarAccesoExitoso($id);
        Auth::login($registro);
        $this->auditoria->registrarSeguro(AuditoriaService::LOGIN, 'usuarios_sistema', $id, null, $id, $usuario);
    }

    public function cerrarSesion(): void
    {
        $id = Auth::id();
        $login = Auth::user()['usuario'] ?? null;
        if ($id !== null) {
            $this->auditoria->registrarSeguro(AuditoriaService::LOGOUT, 'usuarios_sistema', $id, null, $id, $login);
        }
        Auth::logout();
    }
}
