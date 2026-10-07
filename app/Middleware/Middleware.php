<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;

/**
 * Un middleware deja pasar la petición retornando normalmente, o la detiene
 * lanzando HttpException o enviando una redirección.
 */
interface Middleware
{
    public function handle(Request $request): void;
}
