<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\UsuarioSistemaModel;
use App\Services\UsuarioService;

/**
 * CRUD de usuarios del sistema (solo ADMINISTRADOR).
 */
final class UsuarioController extends Controller
{
    public function index(): void
    {
        $q = mb_substr($this->request->str('q'), 0, 100);
        $rol = $this->request->str('rol');
        $rol = in_array($rol, UsuarioService::ROLES, true) ? $rol : null;
        $estado = $this->request->str('estado');
        $activo = match ($estado) {
            'activos'   => 1,
            'inactivos' => 0,
            default     => null,
        };

        $pagina = (new UsuarioSistemaModel())->buscar($q, $rol, $activo, max(1, $this->request->int('page', 1)));

        $this->view('usuarios/index', [
            'titulo'  => 'Usuarios del sistema',
            'pagina'  => $pagina,
            'filtros' => ['q' => $q, 'rol' => $rol ?? '', 'estado' => $activo === null ? '' : $estado],
            'roles'   => UsuarioService::ROLES,
        ]);
    }

    public function crear(): void
    {
        $this->view('usuarios/form', [
            'titulo'  => 'Nuevo usuario',
            'usuario' => null,
            'roles'   => UsuarioService::ROLES,
        ]);
    }

    public function guardar(): void
    {
        try {
            $id = (new UsuarioService())->crear($this->request->post());
        } catch (ValidationException $e) {
            $this->volverConErrores('usuarios/crear', $e->getErrores());
        }

        Session::flash('success', 'Usuario creado correctamente.');
        $this->redirect('usuarios/' . $id . '/editar');
    }

    public function editar(int $id): void
    {
        $usuario = (new UsuarioService())->obtener($id);
        unset($usuario['password_hash']);

        $this->view('usuarios/form', [
            'titulo'   => 'Editar usuario',
            'usuario'  => $usuario,
            'roles'    => UsuarioService::ROLES,
            'esPropio' => $id === Auth::id(),
        ]);
    }

    public function actualizar(int $id): void
    {
        try {
            (new UsuarioService())->actualizar($id, $this->request->post());
        } catch (ValidationException $e) {
            $this->volverConErrores('usuarios/' . $id . '/editar', $e->getErrores());
        }

        Session::flash('success', 'Usuario actualizado correctamente.');
        $this->redirect('usuarios');
    }

    public function mostrarPassword(int $id): void
    {
        $usuario = (new UsuarioService())->obtener($id);

        $this->view('usuarios/password', [
            'titulo'  => 'Restablecer contraseña',
            'usuario' => ['id' => $usuario['id'], 'usuario' => $usuario['usuario'],
                          'nombres' => $usuario['nombres'], 'apellidos' => $usuario['apellidos']],
        ]);
    }

    public function cambiarPassword(int $id): void
    {
        $post = $this->request->post();

        try {
            (new UsuarioService())->restablecerPassword(
                $id,
                is_string($post['password'] ?? null) ? $post['password'] : '',
                is_string($post['password_confirmacion'] ?? null) ? $post['password_confirmacion'] : '',
                !empty($post['debe_cambiar_password'])
            );
        } catch (ValidationException $e) {
            $this->volverConErrores('usuarios/' . $id . '/password', $e->getErrores());
        }

        Session::flash('success', 'Contraseña restablecida correctamente.');
        $this->redirect('usuarios');
    }

    public function alternarActivo(int $id): void
    {
        try {
            $activo = (new UsuarioService())->alternarActivo($id);
            Session::flash('success', $activo ? 'Usuario activado.' : 'Usuario desactivado.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('usuarios');
    }
}
