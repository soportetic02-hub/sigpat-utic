<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\OficinaModel;
use App\Services\OficinaService;

/**
 * Oficinas jerárquicas. ADMINISTRADOR y TECNICO crean y editan;
 * solo ADMINISTRADOR desactiva/activa (borrado lógico).
 */
final class OficinaController extends Controller
{
    public function index(): void
    {
        $vista = $this->request->str('vista') === 'lista' ? 'lista' : 'arbol';
        $q = mb_substr($this->request->str('q'), 0, 100);
        $tipo = $this->request->str('tipo');
        $tipo = in_array($tipo, OficinaService::TIPOS, true) ? $tipo : null;
        $estado = $this->request->str('estado');
        $activo = match ($estado) {
            'activas'   => 1,
            'inactivas' => 0,
            default     => null,
        };
        $inactivasEnArbol = $this->request->bool('inactivas');

        $servicio = new OficinaService();
        $this->view('oficinas/index', [
            'titulo'           => 'Oficinas',
            'vista'            => $vista,
            'arbol'            => $vista === 'arbol' ? $servicio->arbol($inactivasEnArbol) : [],
            'inactivasEnArbol' => $inactivasEnArbol,
            'pagina'           => $vista === 'lista'
                ? (new OficinaModel())->buscar($q, $tipo, $activo, max(1, $this->request->int('page', 1)))
                : null,
            'filtros'          => ['vista' => 'lista', 'q' => $q, 'tipo' => $tipo ?? '', 'estado' => $activo === null ? '' : $estado],
            'tipos'            => OficinaService::TIPOS,
        ]);
    }

    public function crear(): void
    {
        $padre = $this->request->int('padre_id');

        $this->view('oficinas/form', [
            'titulo'   => 'Nueva oficina',
            'oficina'  => null,
            'padreId'  => $padre > 0 ? $padre : null,
            'opciones' => (new OficinaService())->opcionesSelect(),
            'tipos'    => OficinaService::TIPOS,
        ]);
    }

    public function guardar(): void
    {
        try {
            (new OficinaService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('oficinas/crear', $e->getErrores());
        }

        Session::flash('success', 'Oficina registrada correctamente.');
        $this->redirect('oficinas');
    }

    public function editar(int $id): void
    {
        $servicio = new OficinaService();
        $oficina = $servicio->obtener($id);

        $this->view('oficinas/form', [
            'titulo'   => 'Editar oficina',
            'oficina'  => $oficina,
            'padreId'  => $oficina['padre_id'] === null ? null : (int) $oficina['padre_id'],
            'opciones' => $servicio->opcionesSelect($oficina['padre_id'] === null ? null : (int) $oficina['padre_id'], $id),
            'tipos'    => OficinaService::TIPOS,
        ]);
    }

    public function actualizar(int $id): void
    {
        try {
            (new OficinaService())->actualizar($id, $this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('oficinas/' . $id . '/editar', $e->getErrores());
        }

        Session::flash('success', 'Oficina actualizada correctamente.');
        $this->redirect('oficinas');
    }

    public function alternarActivo(int $id): void
    {
        try {
            $activa = (new OficinaService())->alternarActivo($id, $this->usuarioId());
            Session::flash('success', $activa ? 'Oficina activada.' : 'Oficina desactivada.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('oficinas' . ($this->request->str('volver') === 'lista' ? '?vista=lista' : ''));
    }
}
