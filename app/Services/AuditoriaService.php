<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Logger;
use App\Core\Request;
use App\Models\AuditoriaModel;
use InvalidArgumentException;
use Throwable;

/**
 * Registro de auditoría de acciones de los usuarios del sistema.
 *
 * Si se llama dentro de una transacción, el registro forma parte de ella:
 * si la operación se revierte, su auditoría también.
 */
final class AuditoriaService
{
    public const CREAR = 'CREAR';
    public const EDITAR = 'EDITAR';
    public const ELIMINAR = 'ELIMINAR';
    public const LOGIN = 'LOGIN';
    public const LOGIN_FALLIDO = 'LOGIN_FALLIDO';
    public const LOGOUT = 'LOGOUT';
    public const ASIGNAR = 'ASIGNAR';
    public const ASIGNAR_SLOT = 'ASIGNAR_SLOT';
    public const LIBERAR_SLOT = 'LIBERAR_SLOT';
    public const CERRAR = 'CERRAR';
    public const EMITIR = 'EMITIR';
    public const BAJA = 'BAJA';
    public const VER_CONTRASENA = 'VER_CONTRASENA';
    public const FIRMAR = 'FIRMAR';
    public const ENVIAR = 'ENVIAR';
    public const CONFIRMAR_RECEPCION = 'CONFIRMAR_RECEPCION';

    private const ACCIONES = [
        self::CREAR, self::EDITAR, self::ELIMINAR, self::LOGIN, self::LOGIN_FALLIDO, self::LOGOUT,
        self::ASIGNAR, self::ASIGNAR_SLOT, self::LIBERAR_SLOT, self::CERRAR, self::EMITIR, self::BAJA,
        self::VER_CONTRASENA, self::FIRMAR, self::ENVIAR, self::CONFIRMAR_RECEPCION,
    ];

    /** Campos que nunca se guardan en la auditoría. */
    private const CAMPOS_SENSIBLES = ['password', 'password_hash', 'password_confirmacion', 'password_cuenta', 'contrasena_cifrada', '_csrf', 'token', 'token_hash'];

    private AuditoriaModel $modelo;

    public function __construct()
    {
        $this->modelo = new AuditoriaModel();
    }

    /**
     * @param array<string, mixed>|null $antes
     * @param array<string, mixed>|null $despues
     */
    public function registrar(
        string $accion,
        ?string $tabla = null,
        int|string|null $registroId = null,
        ?array $antes = null,
        ?array $despues = null,
        ?int $usuarioId = null,
        ?string $usuarioLogin = null
    ): void {
        if (!in_array($accion, self::ACCIONES, true)) {
            throw new InvalidArgumentException('Acción de auditoría no válida: ' . $accion);
        }

        $usuarioId ??= Auth::id();
        if ($usuarioLogin === null && $usuarioId !== null && Auth::id() === $usuarioId) {
            $usuarioLogin = Auth::user()['usuario'] ?? null;
        }

        $request = Request::actual();

        $this->modelo->insert([
            'usuario_id'    => $usuarioId,
            'usuario_login' => $usuarioLogin !== null ? mb_substr($usuarioLogin, 0, 50) : null,
            'accion'        => $accion,
            'tabla'         => $tabla,
            'registro_id'   => $registroId === null ? null : (string) $registroId,
            'datos_antes'   => $this->aJson($antes),
            'datos_despues' => $this->aJson($despues),
            'ip'            => $request?->ip(),
            'user_agent'    => $request?->userAgent(),
        ]);
    }

    /**
     * Igual que registrar(), pero un fallo al auditar no interrumpe el flujo
     * (se usa en login/logout, donde no hay transacción de negocio).
     *
     * @param array<string, mixed>|null $despues
     */
    public function registrarSeguro(
        string $accion,
        ?string $tabla = null,
        int|string|null $registroId = null,
        ?array $despues = null,
        ?int $usuarioId = null,
        ?string $usuarioLogin = null
    ): void {
        try {
            $this->registrar($accion, $tabla, $registroId, null, $despues, $usuarioId, $usuarioLogin);
        } catch (Throwable $e) {
            Logger::exception($e, 'No se pudo registrar la auditoría');
        }
    }

    /**
     * Compara los JSON "antes" y "después" de un registro de auditoría campo por campo.
     *
     * @return list<array{campo: string, antes: string, despues: string, estado: string}>
     *         estado: igual | modificado | agregado | quitado
     */
    public static function diferencias(?string $antesJson, ?string $despuesJson): array
    {
        $antes = self::decodificar($antesJson);
        $despues = self::decodificar($despuesJson);
        $campos = array_values(array_unique(array_merge(array_keys($antes), array_keys($despues))));

        $filas = [];
        foreach ($campos as $campo) {
            $hayAntes = array_key_exists($campo, $antes);
            $hayDespues = array_key_exists($campo, $despues);
            $a = $hayAntes ? self::comoTexto($antes[$campo]) : '';
            $d = $hayDespues ? self::comoTexto($despues[$campo]) : '';
            $estado = match (true) {
                $hayAntes && $hayDespues => $a === $d ? 'igual' : 'modificado',
                $hayDespues              => $antesJson === null ? 'igual' : 'agregado',
                default                  => $despuesJson === null ? 'igual' : 'quitado',
            };
            $filas[] = ['campo' => (string) $campo, 'antes' => $a, 'despues' => $d, 'estado' => $estado];
        }

        return $filas;
    }

    /** @return array<string|int, mixed> */
    private static function decodificar(?string $json): array
    {
        if ($json === null || $json === '') {
            return [];
        }
        $datos = json_decode($json, true);

        return is_array($datos) ? $datos : ['valor' => $datos];
    }

    private static function comoTexto(mixed $valor): string
    {
        return match (true) {
            $valor === null  => 'NULL',
            is_bool($valor)  => $valor ? 'true' : 'false',
            is_array($valor) => (string) json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
            default          => (string) $valor,
        };
    }

    /** @param array<string, mixed>|null $datos */
    private function aJson(?array $datos): ?string
    {
        if ($datos === null) {
            return null;
        }
        foreach (self::CAMPOS_SENSIBLES as $campo) {
            unset($datos[$campo]);
        }

        return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    }
}
