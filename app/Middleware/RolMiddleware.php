<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use InvalidArgumentException;

/**
 * Restringe la ruta a una lista de roles. Responde 403 si el rol no está permitido.
 * Debe ir después de AuthMiddleware.
 */
final class RolMiddleware implements Middleware
{
    /** @param list<string> $roles */
    public function __construct(private readonly array $roles)
    {
        if ($roles === []) {
            throw new InvalidArgumentException('RolMiddleware requiere al menos un rol.');
        }
    }

    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            throw new HttpException(401);
        }
        if (!Auth::tieneRol(...$this->roles)) {
            throw new HttpException(403);
        }
    }
}
