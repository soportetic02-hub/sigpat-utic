<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;
use Throwable;

/**
 * Renderizado de plantillas PHP de app/Views con layout.
 * Las vistas reciben las variables del arreglo $datos y solo presentan datos:
 * nunca consultan la base de datos.
 */
final class View
{
    /**
     * Renderiza una vista dentro de un layout. La vista queda disponible en el
     * layout como $contenido. Con $layout = null se devuelve la vista sola.
     *
     * @param array<string, mixed> $datos
     */
    public static function render(string $vista, array $datos = [], ?string $layout = 'main'): string
    {
        $contenido = self::renderizarArchivo($vista, $datos);
        if ($layout === null) {
            return $contenido;
        }

        return self::renderizarArchivo('layouts/' . $layout, array_merge($datos, ['contenido' => $contenido]));
    }

    /** @param array<string, mixed> $datos */
    public static function partial(string $vista, array $datos = []): string
    {
        return self::renderizarArchivo($vista, $datos);
    }

    /**
     * Las variables internas llevan prefijo "__" para que no choquen con los
     * datos de la vista (extract con EXTR_SKIP no sobrescribe variables existentes).
     *
     * @param array<string, mixed> $__datos
     */
    private static function renderizarArchivo(string $__vista, array $__datos): string
    {
        if (preg_match('#^[A-Za-z0-9_/\-]+$#', $__vista) !== 1 || str_contains($__vista, '..')) {
            throw new RuntimeException('Nombre de vista no válido: ' . $__vista);
        }

        $__archivo = config('paths.views') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $__vista) . '.php';
        if (!is_file($__archivo)) {
            throw new RuntimeException('No existe la vista: ' . $__vista);
        }

        unset($__datos['__vista'], $__datos['__datos'], $__datos['__archivo'], $__datos['__nivel']);
        extract($__datos, EXTR_SKIP);
        unset($__datos);
        $__nivel = ob_get_level();
        ob_start();
        try {
            require $__archivo;
        } catch (Throwable $e) {
            while (ob_get_level() > $__nivel) {
                ob_end_clean();
            }
            throw $e;
        }

        return (string) ob_get_clean();
    }
}
