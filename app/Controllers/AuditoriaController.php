<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\HttpException;
use App\Models\AuditoriaModel;
use App\Services\AuditoriaService;

/**
 * Visor de auditoría (solo ADMINISTRADOR; lectura).
 */
final class AuditoriaController extends Controller
{
    public function index(): void
    {
        $modelo = new AuditoriaModel();
        $tablas = $modelo->tablas();
        $fecha = static fn (string $v): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
        $accion = $this->request->str('accion');
        $tabla = $this->request->str('tabla');
        $usuario = $this->request->int('usuario_id');
        $registro = $this->request->str('registro_id');

        $filtros = [
            'usuario_id'  => $usuario > 0 ? $usuario : null,
            'accion'      => in_array($accion, AuditoriaModel::ACCIONES, true) ? $accion : null,
            'tabla'       => in_array($tabla, $tablas, true) ? $tabla : null,
            'registro_id' => preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $registro) === 1 ? $registro : '',
            'desde'       => $fecha($this->request->str('desde')),
            'hasta'       => $fecha($this->request->str('hasta')),
        ];

        $this->view('auditoria/index', [
            'titulo'   => 'Auditoría',
            'pagina'   => $modelo->listado($filtros, max(1, $this->request->int('page', 1))),
            'filtros'  => $filtros,
            'tablas'   => $tablas,
            'usuarios' => $modelo->usuarios(),
            'acciones' => AuditoriaModel::ACCIONES,
        ]);
    }

    public function mostrar(int $id): void
    {
        $registro = (new AuditoriaModel())->detalle($id);
        if ($registro === null) {
            throw new HttpException(404, 'El registro de auditoría no existe.');
        }

        $this->view('auditoria/show', [
            'titulo'      => 'Detalle de auditoría',
            'registro'    => $registro,
            'diferencias' => AuditoriaService::diferencias($registro['datos_antes'], $registro['datos_despues']),
        ]);
    }
}
