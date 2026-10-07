<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Petición HTTP actual.
 */
final class Request
{
    private static ?Request $actual = null;

    private string $method;
    private string $path;

    public function __construct()
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $this->method = $method === 'HEAD' ? 'GET' : $method;
        $this->path = $this->resolverPath();
        self::$actual = $this;
    }

    public static function actual(): ?self
    {
        return self::$actual;
    }

    public function method(): string
    {
        return $this->method;
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** Ruta relativa a la aplicación, siempre con "/" inicial y sin "/" final. */
    public function path(): string
    {
        return $this->path;
    }

    public function input(string $clave, mixed $defecto = null): mixed
    {
        return $_POST[$clave] ?? $_GET[$clave] ?? $defecto;
    }

    /** Texto recortado; cadena vacía si no existe o no es escalar. */
    public function str(string $clave, string $defecto = ''): string
    {
        $valor = $this->input($clave, $defecto);

        return is_scalar($valor) ? trim((string) $valor) : $defecto;
    }

    /** Texto recortado o NULL si está vacío. */
    public function strOrNull(string $clave): ?string
    {
        $valor = $this->str($clave);

        return $valor === '' ? null : $valor;
    }

    public function int(string $clave, int $defecto = 0): int
    {
        $valor = filter_var($this->input($clave), FILTER_VALIDATE_INT);

        return $valor === false ? $defecto : (int) $valor;
    }

    public function bool(string $clave): bool
    {
        return filter_var($this->input($clave, false), FILTER_VALIDATE_BOOLEAN);
    }

    /** @return array<string, mixed> */
    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    /** @return array<string, mixed> */
    public function post(): array
    {
        return $_POST;
    }

    public function query(string $clave, mixed $defecto = null): mixed
    {
        return $_GET[$clave] ?? $defecto;
    }

    /** @return array<string, mixed>|null */
    public function file(string $clave): ?array
    {
        return isset($_FILES[$clave]) && is_array($_FILES[$clave]) ? $_FILES[$clave] : null;
    }

    public function header(string $nombre): ?string
    {
        $clave = 'HTTP_' . strtoupper(str_replace('-', '_', $nombre));
        $valor = $_SERVER[$clave] ?? null;

        return is_string($valor) ? $valor : null;
    }

    public function isAjax(): bool
    {
        return strtolower((string) $this->header('X-Requested-With')) === 'xmlhttprequest'
            || str_contains(strtolower((string) $this->header('Accept')), 'application/json');
    }

    public function ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

        return filter_var($ip, FILTER_VALIDATE_IP) !== false ? $ip : '0.0.0.0';
    }

    public function userAgent(): string
    {
        return mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
    }

    /**
     * Quita del REQUEST_URI el directorio del front controller (p. ej. /sigpat-otic/public)
     * o su carpeta padre (acceso vía /sigpat-otic/ reescrito por el .htaccess raíz).
     */
    private function resolverPath(): string
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rawurldecode($uri);

        $scriptDir = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php')));
        $candidatos = [rtrim($scriptDir, '/'), rtrim(str_replace('\\', '/', dirname($scriptDir)), '/')];

        foreach ($candidatos as $base) {
            if ($base !== '' && $base !== '.' && ($uri === $base || str_starts_with($uri, $base . '/'))) {
                $uri = substr($uri, strlen($base));
                break;
            }
        }

        if (str_starts_with($uri, '/index.php')) {
            $uri = substr($uri, strlen('/index.php'));
        }

        $uri = '/' . trim($uri, '/');

        return $uri;
    }
}
