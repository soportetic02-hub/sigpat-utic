<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\UsuarioService;

/**
 * Datos y contraseña del usuario autenticado.
 */
final class PerfilController extends Controller
{
    public function mostrarPassword(): void
    {
        $this->view('perfil/password', [
            'titulo'      => 'Cambiar contraseña',
            'obligatorio' => Auth::debeCambiarPassword(),
        ]);
    }

    public function cambiarPassword(): void
    {
        $post = $this->request->post();

        try {
            (new UsuarioService())->cambiarPasswordPropia(
                $this->usuarioId(),
                is_string($post['password_actual'] ?? null) ? $post['password_actual'] : '',
                is_string($post['password'] ?? null) ? $post['password'] : '',
                is_string($post['password_confirmacion'] ?? null) ? $post['password_confirmacion'] : ''
            );
        } catch (ValidationException $e) {
            $this->volverConErrores('perfil/password', $e->getErrores());
        }

        Session::flash('success', 'Su contraseña se actualizó correctamente.');
        $this->redirect('/');
    }
}
