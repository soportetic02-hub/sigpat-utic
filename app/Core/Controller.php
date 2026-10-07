<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Controlador base: orquesta la petición HTTP y delega la lógica en Services.
 */
abstract class Controller
{
    public function __construct(protected Request $request)
    {
    }

    /** @param array<string, mixed> $datos */
    protected function view(string $vista, array $datos = [], ?string $layout = 'main'): void
    {
        echo View::render($vista, $datos, $layout);
    }

    protected function redirect(string $ruta): never
    {
        Response::redirect($ruta);
    }

    /** @param array<mixed> $datos */
    protected function json(array $datos, int $status = 200): never
    {
        Response::json($datos, $status);
    }

    /**
     * Vuelve al formulario conservando lo enviado (excepto contraseñas) y los errores.
     *
     * @param array<string, string> $errores
     */
    protected function volverConErrores(string $ruta, array $errores, string $mensaje = 'Revise los datos del formulario.'): never
    {
        $old = $this->request->post();
        foreach (array_keys($old) as $campo) {
            if ($campo === '_csrf' || str_contains((string) $campo, 'password')) {
                unset($old[$campo]);
            }
        }
        Session::flash('_old', $old);
        Session::flash('_errors', $errores);
        Session::flash('danger', $mensaje);
        Response::redirect($ruta);
    }

    protected function usuarioId(): int
    {
        $id = Auth::id();
        if ($id === null) {
            throw new HttpException(401);
        }

        return $id;
    }
}
