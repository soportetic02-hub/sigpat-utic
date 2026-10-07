<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\Auth;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\UsuarioSistemaModel;
use Throwable;

/**
 * Gestión de usuarios del sistema: alta, edición, contraseñas y activación.
 */
final class UsuarioService
{
    public const ROLES = [Auth::ADMINISTRADOR, Auth::TECNICO];

    private UsuarioSistemaModel $usuarios;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->usuarios = new UsuarioSistemaModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $usuario = $this->usuarios->find($id);
        if ($usuario === null) {
            throw new HttpException(404, 'El usuario no existe.');
        }

        return $usuario;
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function crear(array $entrada): int
    {
        $datos = $this->validarDatos($entrada, null);
        $password = (string) ($entrada['password'] ?? '');
        $errores = $this->validarPassword($password, (string) ($entrada['password_confirmacion'] ?? ''));
        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        $datos['password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        $datos['activo'] = 1;
        $datos['debe_cambiar_password'] = !empty($entrada['debe_cambiar_password']) ? 1 : 0;

        $this->db->beginTransaction();
        try {
            $id = $this->usuarios->insert($datos);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'usuarios_sistema', $id, null, $this->sinHash($this->usuarios->find($id)));
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * @param array<string, mixed> $entrada
     */
    public function actualizar(int $id, array $entrada): void
    {
        $antes = $this->obtener($id);
        $datos = $this->validarDatos($entrada, $id);

        if ($id === Auth::id() && $datos['rol'] !== $antes['rol']) {
            throw ValidationException::campo('rol', 'No puede cambiar su propio rol.');
        }
        if ($antes['rol'] === Auth::ADMINISTRADOR && $datos['rol'] !== Auth::ADMINISTRADOR
            && (int) $antes['activo'] === 1 && $this->usuarios->contarAdministradoresActivos($id) === 0) {
            throw ValidationException::campo('rol', 'Debe existir al menos un administrador activo.');
        }

        $this->db->beginTransaction();
        try {
            $this->usuarios->update($id, $datos);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'usuarios_sistema', $id,
                $this->sinHash($antes), $this->sinHash($this->usuarios->find($id)));
            $this->db->commit();
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Un administrador establece una nueva contraseña para otro usuario. */
    public function restablecerPassword(int $id, string $password, string $confirmacion, bool $exigirCambio): void
    {
        $usuario = $this->obtener($id);
        $errores = $this->validarPassword($password, $confirmacion);
        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        $this->db->beginTransaction();
        try {
            $this->usuarios->update($id, [
                'password_hash'         => password_hash($password, PASSWORD_BCRYPT),
                'debe_cambiar_password' => $exigirCambio ? 1 : 0,
                'intentos_fallidos'     => 0,
                'bloqueado_hasta'       => null,
            ]);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'usuarios_sistema', $id,
                ['usuario' => $usuario['usuario']],
                ['usuario' => $usuario['usuario'], 'cambio' => 'Contraseña restablecida por administrador', 'debe_cambiar_password' => $exigirCambio ? 1 : 0]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** El usuario autenticado cambia su propia contraseña. */
    public function cambiarPasswordPropia(int $id, string $actual, string $nueva, string $confirmacion): void
    {
        $usuario = $this->obtener($id);
        if (!password_verify($actual, (string) $usuario['password_hash'])) {
            throw ValidationException::campo('password_actual', 'La contraseña actual no es correcta.');
        }
        $errores = $this->validarPassword($nueva, $confirmacion);
        if ($errores === [] && password_verify($nueva, (string) $usuario['password_hash'])) {
            $errores['password'] = 'La nueva contraseña debe ser distinta de la actual.';
        }
        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        $this->db->beginTransaction();
        try {
            $this->usuarios->update($id, [
                'password_hash'         => password_hash($nueva, PASSWORD_BCRYPT),
                'debe_cambiar_password' => 0,
            ]);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'usuarios_sistema', $id,
                null, ['usuario' => $usuario['usuario'], 'cambio' => 'Cambio de contraseña propia']);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        Auth::refrescar();
    }

    /** Activa o desactiva (borrado lógico) un usuario. Devuelve el nuevo estado. */
    public function alternarActivo(int $id): bool
    {
        $usuario = $this->obtener($id);
        $nuevoEstado = (int) $usuario['activo'] === 1 ? 0 : 1;

        if ($nuevoEstado === 0) {
            if ($id === Auth::id()) {
                throw ValidationException::campo('activo', 'No puede desactivar su propia cuenta.');
            }
            if ($usuario['rol'] === Auth::ADMINISTRADOR && $this->usuarios->contarAdministradoresActivos($id) === 0) {
                throw ValidationException::campo('activo', 'Debe existir al menos un administrador activo.');
            }
        }

        $cambios = ['activo' => $nuevoEstado];
        if ($nuevoEstado === 1) {
            $cambios['intentos_fallidos'] = 0;
            $cambios['bloqueado_hasta'] = null;
        }

        $this->db->beginTransaction();
        try {
            $this->usuarios->update($id, $cambios);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'usuarios_sistema', $id,
                ['usuario' => $usuario['usuario'], 'activo' => (int) $usuario['activo']],
                ['usuario' => $usuario['usuario'], 'activo' => $nuevoEstado]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $nuevoEstado === 1;
    }

    /**
     * Reglas: mínimo 8 caracteres (máx. 72 por BCRYPT), con mayúscula, minúscula y número.
     *
     * @return array<string, string>
     */
    public function validarPassword(string $password, string $confirmacion): array
    {
        $errores = [];
        if (strlen($password) < 8 || strlen($password) > 72) {
            $errores['password'] = 'La contraseña debe tener entre 8 y 72 caracteres.';
        } elseif (preg_match('/[A-ZÁÉÍÓÚÑ]/u', $password) !== 1
            || preg_match('/[a-záéíóúñ]/u', $password) !== 1
            || preg_match('/[0-9]/', $password) !== 1) {
            $errores['password'] = 'La contraseña debe incluir al menos una mayúscula, una minúscula y un número.';
        }
        if ($password !== $confirmacion) {
            $errores['password_confirmacion'] = 'La confirmación no coincide con la contraseña.';
        }

        return $errores;
    }

    /**
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    private function validarDatos(array $entrada, ?int $id): array
    {
        $texto = static fn (string $campo): string => trim((string) ($entrada[$campo] ?? ''));
        $datos = [
            'usuario'   => strtolower($texto('usuario')),
            'nombres'   => $texto('nombres'),
            'apellidos' => $texto('apellidos'),
            'dni'       => $texto('dni') !== '' ? $texto('dni') : null,
            'cargo'     => $texto('cargo') !== '' ? $texto('cargo') : null,
            'email'     => $texto('email') !== '' ? strtolower($texto('email')) : null,
            'rol'       => $texto('rol'),
            'puede_firmar' => !empty($entrada['puede_firmar']) ? 1 : 0,
        ];

        $errores = [];
        if (preg_match('/^[a-z0-9._-]{3,50}$/', $datos['usuario']) !== 1) {
            $errores['usuario'] = 'El usuario debe tener de 3 a 50 caracteres: letras, números, punto, guion o guion bajo.';
        } elseif ($this->usuarios->existeValor('usuario', $datos['usuario'], $id)) {
            $errores['usuario'] = 'Ya existe un usuario con ese nombre de usuario.';
        }
        if ($datos['nombres'] === '' || mb_strlen($datos['nombres']) > 100) {
            $errores['nombres'] = 'Ingrese los nombres (máximo 100 caracteres).';
        }
        if ($datos['apellidos'] === '' || mb_strlen($datos['apellidos']) > 100) {
            $errores['apellidos'] = 'Ingrese los apellidos (máximo 100 caracteres).';
        }
        if ($datos['dni'] !== null) {
            if (preg_match('/^[0-9]{8}$/', $datos['dni']) !== 1) {
                $errores['dni'] = 'El DNI debe tener 8 dígitos.';
            } elseif ($this->usuarios->existeValor('dni', $datos['dni'], $id)) {
                $errores['dni'] = 'Ya existe un usuario con ese DNI.';
            }
        }
        if ($datos['cargo'] !== null && mb_strlen($datos['cargo']) > 150) {
            $errores['cargo'] = 'El cargo admite como máximo 150 caracteres.';
        }
        if ($datos['email'] !== null) {
            if (filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($datos['email']) > 150) {
                $errores['email'] = 'Ingrese un correo electrónico válido.';
            } elseif ($this->usuarios->existeValor('email', $datos['email'], $id)) {
                $errores['email'] = 'Ya existe un usuario con ese correo.';
            }
        }
        if (!in_array($datos['rol'], self::ROLES, true)) {
            $errores['rol'] = 'Seleccione un rol válido.';
        }
        if ($datos['puede_firmar'] === 1 && $datos['dni'] === null && !isset($errores['dni'])) {
            // El DNI se compara con el del certificado del DNIe al subir un acta firmada.
            $errores['dni'] = 'Para autorizar la firma de actas registre el DNI del usuario.';
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return $datos;
    }

    /**
     * @param array<string, mixed>|null $fila
     * @return array<string, mixed>|null
     */
    private function sinHash(?array $fila): ?array
    {
        if ($fila !== null) {
            unset($fila['password_hash']);
        }

        return $fila;
    }

    private function traducirError(DatabaseException $e): Throwable
    {
        if ($e->esDuplicado()) {
            return match ($e->getConstraint()) {
                'uq_usuarios_sistema_usuario' => ValidationException::campo('usuario', 'Ya existe un usuario con ese nombre de usuario.'),
                'uq_usuarios_sistema_email'   => ValidationException::campo('email', 'Ya existe un usuario con ese correo.'),
                'uq_usuarios_sistema_dni'     => ValidationException::campo('dni', 'Ya existe un usuario con ese DNI.'),
                default                       => ValidationException::campo('usuario', 'Ya existe un registro con esos datos.'),
            };
        }

        return $e;
    }
}
