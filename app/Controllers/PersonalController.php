<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\EquipoModel;
use App\Models\HistorialAsignacionModel;
use App\Models\PersonalModel;
use App\Services\AsignacionService;
use App\Services\OficinaService;
use App\Services\PersonalService;

/**
 * Personal institucional. ADMINISTRADOR y TECNICO crean, editan y asignan equipos;
 * solo ADMINISTRADOR desactiva/activa (borrado lógico).
 */
final class PersonalController extends Controller
{
    public function index(): void
    {
        $q = mb_substr($this->request->str('q'), 0, 100);
        $oficinaId = $this->request->int('oficina_id');
        $estado = $this->request->str('estado', 'activos');
        $activo = match ($estado) {
            'inactivos' => 0,
            'todos'     => null,
            default     => 1,
        };

        $this->view('personal/index', [
            'titulo'   => 'Personal',
            'pagina'   => (new PersonalModel())->buscar($q, $oficinaId > 0 ? $oficinaId : null, $activo, max(1, $this->request->int('page', 1))),
            'filtros'  => ['q' => $q, 'oficina_id' => $oficinaId > 0 ? $oficinaId : '', 'estado' => $estado],
            'oficinas' => (new OficinaService())->opcionesSelect(),
        ]);
    }

    public function mostrar(int $id): void
    {
        $persona = (new PersonalService())->obtener($id);

        $this->view('personal/show', [
            'titulo'    => 'Detalle del personal',
            'persona'   => $persona,
            'equipos'   => (new EquipoModel())->porPersonal($id),
            'historial' => (new HistorialAsignacionModel())->porPersonal($id),
        ]);
    }

    public function crear(): void
    {
        $oficina = $this->request->int('oficina_id');

        $this->view('personal/form', [
            'titulo'    => 'Registrar personal',
            'persona'   => null,
            'oficinaId' => $oficina > 0 ? $oficina : null,
            'oficinas'  => (new OficinaService())->opcionesSelect(),
            'equipos'   => [],
        ]);
    }

    public function guardar(): void
    {
        try {
            $id = (new PersonalService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('personal/crear', $e->getErrores());
        }

        Session::flash('success', 'Personal registrado correctamente.');
        $this->redirect('personal/' . $id);
    }

    public function editar(int $id): void
    {
        $persona = (new PersonalService())->obtener($id);

        $this->view('personal/form', [
            'titulo'    => 'Editar personal',
            'persona'   => $persona,
            'oficinaId' => (int) $persona['oficina_id'],
            'oficinas'  => (new OficinaService())->opcionesSelect((int) $persona['oficina_id']),
            'equipos'   => (new EquipoModel())->porPersonal($id),
        ]);
    }

    public function actualizar(int $id): void
    {
        $decisiones = $this->request->post()['equipos_accion'] ?? [];

        try {
            $resumen = (new PersonalService())->actualizar(
                $id,
                $this->request->post(),
                is_array($decisiones) ? $decisiones : [],
                $this->usuarioId()
            );
        } catch (ValidationException $e) {
            $this->volverConErrores('personal/' . $id . '/editar', $e->getErrores(), $e->getMessage());
        }

        $mensaje = 'Datos del personal actualizados.';
        if ($resumen['trasladados'] + $resumen['liberados'] > 0) {
            $mensaje .= sprintf(
                ' Rotación: %d equipo(s) trasladado(s) con la persona y %d dejado(s) en su oficina sin responsable.',
                $resumen['trasladados'],
                $resumen['liberados']
            );
        }
        Session::flash('success', $mensaje);
        $this->redirect('personal/' . $id);
    }

    public function alternarActivo(int $id): void
    {
        try {
            $activo = (new PersonalService())->alternarActivo($id, $this->usuarioId());
            Session::flash('success', $activo ? 'Personal activado.' : 'Personal desactivado.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('personal/' . $id);
    }

    /** JSON: equipos que pueden asignarse a la persona (buscador del modal). */
    public function equiposDisponibles(int $id): void
    {
        (new PersonalService())->obtener($id);
        $q = mb_substr($this->request->str('q'), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'equipos' => []]);
        }

        $this->json(['ok' => true, 'equipos' => (new EquipoModel())->buscarAsignables($q, $id)]);
    }

    /** JSON: autocompletado de personal activo (lo usarán las actas de mantenimiento). */
    public function autocompletar(): void
    {
        $q = mb_substr($this->request->str('q'), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'personal' => []]);
        }

        $this->json(['ok' => true, 'personal' => (new PersonalModel())->autocompletar($q)]);
    }

    public function asignarEquipo(int $id): void
    {
        $equipoId = $this->request->int('equipo_id');
        $trasladar = $this->request->str('ubicacion', 'persona') === 'persona';

        try {
            if ($equipoId <= 0) {
                throw ValidationException::campo('equipo_id', 'Seleccione un equipo.');
            }
            $cambio = (new AsignacionService())->asignarAPersonal(
                $equipoId,
                $id,
                $this->usuarioId(),
                $trasladar,
                $this->request->strOrNull('observacion')
            );
            Session::flash($cambio ? 'success' : 'info', $cambio ? 'Equipo asignado correctamente.' : 'El equipo ya estaba asignado a esta persona.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('personal/' . $id);
    }

    public function desvincularEquipo(int $id, int $equipoId): void
    {
        $equipo = (new EquipoModel())->find($equipoId);
        if ($equipo === null || (int) ($equipo['personal_id'] ?? 0) !== $id) {
            Session::flash('danger', 'El equipo no está asignado a esta persona.');
            $this->redirect('personal/' . $id);
        }

        try {
            (new AsignacionService())->desvincular($equipoId, $this->usuarioId(), $this->request->strOrNull('observacion'));
            Session::flash('success', 'Equipo desvinculado. Permanece en su oficina sin responsable.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('personal/' . $id);
    }
}
