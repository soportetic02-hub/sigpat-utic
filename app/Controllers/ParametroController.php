<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\CatalogoSoftwareModel;
use App\Models\ChecklistItemModel;
use App\Models\ParametroModel;
use App\Services\ConfiguracionService;

/**
 * Parámetros del sistema y catálogos de software y checklist (solo ADMINISTRADOR).
 */
final class ParametroController extends Controller
{
    private const PESTANAS = ['parametros', 'software', 'checklist'];

    public function index(): void
    {
        $pestana = $this->request->str('pestana', 'parametros');

        $this->view('parametros/index', [
            'titulo'      => 'Parámetros y catálogos',
            'pestana'     => in_array($pestana, self::PESTANAS, true) ? $pestana : 'parametros',
            'parametros'  => (new ParametroModel())->todos(),
            'software'    => (new CatalogoSoftwareModel())->listadoCompleto(),
            'checklist'   => (new ChecklistItemModel())->listadoCompleto(),
            'categorias'  => ChecklistItemModel::CATEGORIAS,
            'tiposEquipo' => ConfiguracionService::TIPOS_EQUIPO,
        ]);
    }

    public function guardarParametros(): void
    {
        $valores = $this->request->post()['param'] ?? [];
        try {
            $cambios = (new ConfiguracionService())->guardarParametros(is_array($valores) ? $valores : [], $this->usuarioId());
            Session::flash('success', $cambios === 0 ? 'No había cambios que guardar.' : sprintf('%d parámetro(s) actualizado(s).', $cambios));
        } catch (ValidationException $e) {
            $this->volverConErrores('parametros', $e->getErrores());
        }

        $this->redirect('parametros');
    }

    public function crearSoftware(): void
    {
        $this->guardar('software', null);
    }

    public function actualizarSoftware(int $id): void
    {
        $this->guardar('software', $id);
    }

    public function crearChecklist(): void
    {
        $this->guardar('checklist', null);
    }

    public function actualizarChecklist(int $id): void
    {
        $this->guardar('checklist', $id);
    }

    public function alternarSoftware(int $id): void
    {
        $this->alternar('software', $id);
    }

    public function alternarChecklist(int $id): void
    {
        $this->alternar('checklist', $id);
    }

    private function guardar(string $catalogo, ?int $id): void
    {
        $servicio = new ConfiguracionService();
        try {
            if ($catalogo === 'software') {
                $servicio->guardarSoftware($id, $this->request->post(), $this->usuarioId());
            } else {
                $servicio->guardarChecklist($id, $this->request->post(), $this->usuarioId());
            }
            Session::flash('success', $id === null ? 'Elemento agregado al catálogo.' : 'Elemento actualizado.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('parametros?pestana=' . $catalogo);
    }

    private function alternar(string $catalogo, int $id): void
    {
        $activo = (new ConfiguracionService())->alternarActivo($catalogo, $id, $this->usuarioId());
        Session::flash('success', $activo ? 'Elemento activado.' : 'Elemento desactivado: ya no aparecerá en las nuevas actas.');
        $this->redirect('parametros?pestana=' . $catalogo);
    }
}
