<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\CsrfMiddleware;
use App\Middleware\Middleware;
use App\Middleware\RolMiddleware;
use InvalidArgumentException;

/**
 * Enrutador con rutas GET/POST, parámetros {nombre} y grupos con prefijo y middleware.
 *
 * Middleware por alias:
 *   'auth'                        -> AuthMiddleware
 *   'rol:ADMINISTRADOR'           -> RolMiddleware(['ADMINISTRADOR'])
 *   'rol:ADMINISTRADOR,TECNICO'   -> RolMiddleware(['ADMINISTRADOR', 'TECNICO'])
 *   'csrf'                        -> CsrfMiddleware
 * Toda ruta POST aplica CsrfMiddleware automáticamente.
 */
final class Router
{
    /** @var list<array{method: string, path: string, regex: string, params: list<string>, handler: array{0: class-string, 1: string}, middleware: list<string>}> */
    private array $rutas = [];

    /** @var list<array{prefix: string, middleware: list<string>}> */
    private array $grupos = [];

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function get(string $path, array $handler, array $middleware = []): void
    {
        $this->agregar('GET', $path, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    public function post(string $path, array $handler, array $middleware = []): void
    {
        $this->agregar('POST', $path, $handler, $middleware);
    }

    /**
     * @param array{prefix?: string, middleware?: list<string>} $atributos
     */
    public function group(array $atributos, callable $definir): void
    {
        $this->grupos[] = [
            'prefix'     => trim($atributos['prefix'] ?? '', '/'),
            'middleware' => $atributos['middleware'] ?? [],
        ];
        $definir($this);
        array_pop($this->grupos);
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path();
        $metodoPermitido = false;

        foreach ($this->rutas as $ruta) {
            if (preg_match($ruta['regex'], $path, $coincidencias) !== 1) {
                continue;
            }
            if ($ruta['method'] !== $request->method()) {
                $metodoPermitido = true;
                continue;
            }

            $argumentos = [];
            foreach ($ruta['params'] as $nombre) {
                $valor = $coincidencias[$nombre];
                $argumentos[] = ctype_digit($valor) && strlen($valor) < 10 ? (int) $valor : $valor;
            }

            $middleware = $ruta['middleware'];
            if ($ruta['method'] === 'POST' && !in_array('csrf', $middleware, true)) {
                $middleware[] = 'csrf';
            }
            foreach ($middleware as $alias) {
                $this->resolverMiddleware($alias)->handle($request);
            }

            [$clase, $metodo] = $ruta['handler'];
            $controlador = new $clase($request);
            $controlador->{$metodo}(...$argumentos);

            return;
        }

        throw new HttpException($metodoPermitido ? 405 : 404);
    }

    /**
     * @param array{0: class-string, 1: string} $handler
     * @param list<string> $middleware
     */
    private function agregar(string $method, string $path, array $handler, array $middleware): void
    {
        $prefijos = array_filter(array_column($this->grupos, 'prefix'), static fn (string $p): bool => $p !== '');
        $segmentos = array_merge($prefijos, [trim($path, '/')]);
        $completa = '/' . trim(implode('/', array_filter($segmentos, static fn (string $s): bool => $s !== '')), '/');

        $mwGrupos = [];
        foreach ($this->grupos as $grupo) {
            $mwGrupos = array_merge($mwGrupos, $grupo['middleware']);
        }

        // Cada segmento literal se escapa; cada {nombre} se convierte en un grupo con nombre.
        // Los parámetros "id", "...Id" y "..._id" solo aceptan dígitos.
        $params = [];
        $partes = preg_split('#(\{[a-zA-Z_][a-zA-Z0-9_]*\})#', $completa, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$completa];
        $regex = '';
        foreach ($partes as $parte) {
            if (preg_match('#^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$#', $parte, $m) === 1) {
                $params[] = $m[1];
                $numerico = $m[1] === 'id' || str_ends_with($m[1], 'Id') || str_ends_with($m[1], '_id');
                $regex .= '(?P<' . $m[1] . '>' . ($numerico ? '[0-9]+' : '[^/]+') . ')';
            } else {
                $regex .= preg_quote($parte, '#');
            }
        }

        $this->rutas[] = [
            'method'     => $method,
            'path'       => $completa,
            'regex'      => '#^' . $regex . '$#',
            'params'     => $params,
            'handler'    => $handler,
            'middleware' => array_values(array_unique(array_merge($mwGrupos, $middleware))),
        ];
    }

    private function resolverMiddleware(string $alias): Middleware
    {
        [$nombre, $argumento] = array_pad(explode(':', $alias, 2), 2, '');

        return match ($nombre) {
            'auth'  => new AuthMiddleware(),
            'csrf'  => new CsrfMiddleware(),
            'rol'   => new RolMiddleware(array_values(array_filter(array_map('trim', explode(',', $argumento))))),
            default => throw new InvalidArgumentException('Middleware desconocido: ' . $alias),
        };
    }
}
