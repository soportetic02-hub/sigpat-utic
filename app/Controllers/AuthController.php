<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Services\AutenticacionService;

final class AuthController extends Controller
{
    public function mostrarLogin(): void
    {
        if (Auth::check()) {
            $this->redirect('/');
        }

        $this->view('auth/login', ['titulo' => 'Iniciar sesión'], 'auth');
    }

    public function login(): void
    {
        $usuario = $this->request->str('usuario');
        $password = $this->request->post()['password'] ?? '';

        try {
            (new AutenticacionService())->iniciarSesion(mb_substr($usuario, 0, 50), is_string($password) ? $password : '');
        } catch (ValidationException $e) {
            Session::flash('_old', ['usuario' => $usuario]);
            Session::flash('danger', $e->getMessage());
            $this->redirect('login');
        }

        $destino = Session::get('url_intentada');
        Session::remove('url_intentada');
        Session::flash('success', 'Bienvenido(a), ' . Auth::nombreCompleto() . '.');

        $this->redirect(is_string($destino) && str_starts_with($destino, '/') && $destino !== '/login' ? $destino : '/');
    }

    public function logout(): void
    {
        (new AutenticacionService())->cerrarSesion();
        Session::flash('success', 'Cerró sesión correctamente.');
        $this->redirect('login');
    }
}
