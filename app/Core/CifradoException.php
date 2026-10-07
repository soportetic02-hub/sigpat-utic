<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

/**
 * Falta la APP_KEY, no es válida o un valor cifrado no se puede descifrar.
 */
final class CifradoException extends RuntimeException
{
}
