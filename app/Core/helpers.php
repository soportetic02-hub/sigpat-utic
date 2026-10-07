<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;

if (!function_exists('base_path')) {
    function base_path(string $ruta = ''): string
    {
        $base = dirname(__DIR__, 2);

        return $ruta === '' ? $base : $base . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $ruta), '\\/');
    }
}

if (!function_exists('config')) {
    /**
     * Lee la configuración con notación de puntos: config('app.url').
     */
    function config(string $clave, mixed $defecto = null): mixed
    {
        static $config = null;
        if ($config === null) {
            // Ámbito aislado: las variables de config.php no deben pisar $clave ni $defecto.
            $config = (static fn (string $archivo): array => require $archivo)(base_path('app/Config/config.php'));
        }

        $valor = $config;
        foreach (explode('.', $clave) as $parte) {
            if (!is_array($valor) || !array_key_exists($parte, $valor)) {
                return $defecto;
            }
            $valor = $valor[$parte];
        }

        return $valor;
    }
}

if (!function_exists('paleta')) {
    /**
     * Color de la paleta institucional (app/Config/paleta.php) para PDF, Word y correos: paleta('guinda') => '#7a1334'.
     * Con $sinAlmohadilla = true devuelve '7a1334' (formato de PhpWord).
     */
    function paleta(string $clave, bool $sinAlmohadilla = false): string
    {
        static $paleta = null;
        if ($paleta === null) {
            $paleta = require base_path('app/Config/paleta.php');
        }
        if (!isset($paleta[$clave])) {
            throw new InvalidArgumentException('Color de paleta desconocido: ' . $clave);
        }

        return $sinAlmohadilla ? ltrim($paleta[$clave], '#') : $paleta[$clave];
    }
}

if (!function_exists('e')) {
    /** Escapa texto para HTML (ENT_QUOTES, UTF-8). */
    function e(mixed $valor): string
    {
        if ($valor === null) {
            return '';
        }
        if (is_bool($valor)) {
            $valor = $valor ? '1' : '0';
        }

        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    /**
     * URL absoluta a partir de APP_URL: url('usuarios/5/editar', ['q' => 'x']).
     *
     * @param array<string, scalar|null> $query
     */
    function url(string $ruta = '', array $query = []): string
    {
        $url = config('app.url') . '/' . ltrim($ruta, '/');
        $query = array_filter($query, static fn ($v) => $v !== null && $v !== '');
        if ($query !== []) {
            $url .= '?' . http_build_query($query);
        }

        return $url;
    }
}

if (!function_exists('asset')) {
    /** URL a un recurso de public/assets con versión por fecha de modificación. */
    function asset(string $ruta): string
    {
        $ruta = ltrim($ruta, '/');
        $archivo = base_path('public/assets/' . $ruta);
        $version = is_file($archivo) ? (string) filemtime($archivo) : '1';

        return config('app.url') . '/assets/' . $ruta . '?v=' . $version;
    }
}

if (!function_exists('old')) {
    /** Valor enviado en el formulario anterior (tras un error de validación). */
    function old(string $campo, mixed $defecto = ''): string
    {
        $old = Session::getFlashData('_old', []);
        $valor = is_array($old) && array_key_exists($campo, $old) ? $old[$campo] : $defecto;

        return is_scalar($valor) || $valor === null ? (string) $valor : '';
    }
}

if (!function_exists('old_array')) {
    /**
     * Arreglo enviado en el formulario anterior (filas dinámicas), o $defecto.
     *
     * @param array<mixed> $defecto
     * @return array<mixed>
     */
    function old_array(string $campo, array $defecto = []): array
    {
        $old = Session::getFlashData('_old', []);

        return is_array($old) && isset($old[$campo]) && is_array($old[$campo]) ? $old[$campo] : $defecto;
    }
}

if (!function_exists('hay_old')) {
    /** true si se está volviendo a un formulario tras un error de validación. */
    function hay_old(): bool
    {
        $old = Session::getFlashData('_old', []);

        return is_array($old) && $old !== [];
    }
}

if (!function_exists('error')) {
    /** Mensaje de error de validación de un campo, si existe. */
    function error(string $campo): ?string
    {
        $errores = Session::getFlashData('_errors', []);

        return is_array($errores) && isset($errores[$campo]) ? (string) $errores[$campo] : null;
    }
}

if (!function_exists('flash')) {
    /**
     * Con mensaje: guarda un mensaje flash (success, danger, warning, info).
     * Sin mensaje: devuelve y consume el mensaje de ese tipo.
     */
    function flash(string $tipo, ?string $mensaje = null): ?string
    {
        if ($mensaje !== null) {
            Session::flash($tipo, $mensaje);

            return null;
        }

        $valor = Session::getFlashData($tipo);

        return is_string($valor) ? $valor : null;
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Csrf::token();
    }
}

if (!function_exists('auth_user')) {
    /** @return array<string, mixed>|null */
    function auth_user(): ?array
    {
        return Auth::user();
    }
}

if (!function_exists('has_role')) {
    function has_role(string ...$roles): bool
    {
        return Auth::tieneRol(...$roles);
    }
}

if (!function_exists('fecha')) {
    /** Formatea una fecha/fecha-hora de la BD como dd/mm/aaaa [hh:mm]. */
    function fecha(?string $valor, bool $conHora = false): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        $ts = strtotime($valor);

        return $ts === false ? '' : date($conHora ? 'd/m/Y H:i' : 'd/m/Y', $ts);
    }
}

if (!function_exists('clase_estado')) {
    /** Clase de badge Bootstrap para un estado operativo de equipo. */
    function clase_estado(?string $estado): string
    {
        return match ($estado) {
            'OPERATIVO'        => 'text-bg-success',
            'EN_MANTENIMIENTO' => 'text-bg-warning',
            'INOPERATIVO'      => 'text-bg-danger',
            'DE_BAJA'          => 'text-bg-dark',
            default            => 'text-bg-light border',
        };
    }
}

if (!function_exists('clase_condicion')) {
    /** Clase de badge Bootstrap para la condición física de un equipo. */
    function clase_condicion(?string $condicion): string
    {
        return match ($condicion) {
            'BUENO'   => 'text-bg-success',
            'REGULAR' => 'text-bg-warning',
            'MALO'    => 'text-bg-danger',
            default   => 'text-bg-light border',
        };
    }
}

if (!function_exists('clase_envio')) {
    /** Clase de badge Bootstrap para el estado de envío por correo de un acta. */
    function clase_envio(?string $estado): string
    {
        return match ($estado) {
            'ENVIADO'  => 'text-bg-info',
            'RECIBIDO' => 'text-bg-success',
            'ERROR'    => 'text-bg-danger',
            default    => 'text-bg-light border',
        };
    }
}

if (!function_exists('etiqueta')) {
    /** Convierte un valor de ENUM en texto legible: EN_MANTENIMIENTO -> "En mantenimiento". */
    function etiqueta(?string $valor): string
    {
        if ($valor === null || $valor === '') {
            return '';
        }
        // Siglas que deben mantenerse en mayúsculas.
        if (in_array($valor, ['PC', 'HDD', 'SSD', 'NVME', 'RAM', 'OEM', 'OK'], true)) {
            return $valor;
        }
        // Valores de ENUM que se guardan sin tilde en la BD.
        $conTilde = [
            'REPARACION' => 'Reparación', 'INSTALACION' => 'Instalación', 'REASIGNACION' => 'Reasignación',
            'DESVINCULACION' => 'Desvinculación', 'ROTACION' => 'Rotación', 'ASIGNACION' => 'Asignación',
            'FISICO' => 'Físico', 'LOGICO' => 'Lógico', 'DIRECCION' => 'Dirección',
        ];
        if (isset($conTilde[$valor])) {
            return $conTilde[$valor];
        }
        $texto = mb_strtolower(str_replace('_', ' ', $valor));

        return mb_strtoupper(mb_substr($texto, 0, 1)) . mb_substr($texto, 1);
    }
}

if (!function_exists('is_active_path')) {
    /** true si la ruta actual empieza por el prefijo indicado (para marcar el menú). */
    function is_active_path(string $prefijo): bool
    {
        $actual = Request::actual()?->path() ?? '/';
        $prefijo = '/' . trim($prefijo, '/');

        return $prefijo === '/' ? $actual === '/' : ($actual === $prefijo || str_starts_with($actual, $prefijo . '/'));
    }
}
