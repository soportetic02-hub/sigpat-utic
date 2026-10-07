<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\InformeEquipoModel;
use App\Models\InformeModel;
use App\Services\EvidenciaService;
use App\Services\InformeService;

/**
 * Informes técnicos (ADMINISTRADOR y TECNICO).
 */
final class InformeController extends Controller
{
    public function index(): void
    {
        $fecha = static fn (string $v): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
        $accion = $this->request->str('accion');
        $estado = $this->request->str('estado');
        $filtros = [
            'desde'  => $fecha($this->request->str('desde')),
            'hasta'  => $fecha($this->request->str('hasta')),
            'accion' => in_array($accion, InformeModel::ACCIONES, true) ? $accion : null,
            'estado' => in_array($estado, InformeModel::ESTADOS, true) ? $estado : null,
            'q'      => mb_substr($this->request->str('q'), 0, 60),
        ];

        $this->view('informes/index', [
            'titulo'   => 'Informes técnicos',
            'pagina'   => (new InformeModel())->listado($filtros, max(1, $this->request->int('page', 1))),
            'filtros'  => $filtros,
            'acciones' => InformeModel::ACCIONES,
        ]);
    }

    /** Formulario nuevo; acepta ?equipo_id=5 o ?equipo_id[]=5&equipo_id[]=7 para precargar equipos. */
    public function crear(): void
    {
        $solicitados = $this->request->query('equipo_id', []);
        $ids = is_array($solicitados) ? $solicitados : [$solicitados];

        $this->view('informes/form', $this->datosFormulario(null, $this->filasPrecargadas(array_filter($ids, 'is_scalar'), [])));
    }

    public function guardar(): void
    {
        try {
            $id = (new InformeService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('informes/crear', $e->getErrores());
        }

        Session::flash('success', 'Informe guardado como borrador. Ahora puede adjuntar las evidencias fotográficas y emitirlo.');
        $this->redirect('informes/' . $id);
    }

    public function mostrar(int $id): void
    {
        $datos = (new InformeService())->obtenerCompleto($id);

        $this->view('informes/show', $datos + [
            'titulo'      => 'Informe técnico',
            'puedeAnular' => $datos['informe']['estado'] === 'BORRADOR'
                && ((int) $datos['informe']['created_by'] === Auth::id() || Auth::esAdministrador()),
            'maxEnvio'    => EvidenciaService::MAX_POR_ENVIO,
        ]);
    }

    public function editar(int $id): void
    {
        $datos = (new InformeService())->obtenerCompleto($id);
        if ($datos['informe']['estado'] !== 'BORRADOR') {
            Session::flash('warning', 'El informe ya fue emitido y no puede modificarse.');
            $this->redirect('informes/' . $id);
        }

        $this->view('informes/form', $this->datosFormulario($datos['informe'], $this->filasPrecargadas([], $datos['equipos'])));
    }

    public function actualizar(int $id): void
    {
        $servicio = new InformeService();
        try {
            $servicio->actualizar($id, $this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('informes/' . $id . '/editar', $e->getErrores());
        }

        if ($this->request->str('accion') === 'emitir') {
            $this->emitir($id);
        }
        Session::flash('success', 'Borrador actualizado.');
        $this->redirect('informes/' . $id);
    }

    public function emitir(int $id): void
    {
        try {
            $numero = (new InformeService())->emitir($id, $this->usuarioId());
            Session::flash('success', 'Informe emitido con el N° ' . $numero . '. El PDF y el Word están disponibles.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('informes/' . $id);
    }

    public function anular(int $id): void
    {
        try {
            (new InformeService())->anular($id, $this->usuarioId());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('informes/' . $id);
        }

        Session::flash('success', 'Borrador anulado junto con sus evidencias.');
        $this->redirect('informes');
    }

    public function subirEvidencias(int $id): void
    {
        $post = $this->request->post();
        try {
            $cantidad = (new InformeService())->subirEvidencias(
                $id,
                EvidenciaService::normalizarMultiples($this->request->file('fotos')),
                is_array($post['descripciones'] ?? null) ? $post['descripciones'] : [],
                is_array($post['equipos_foto'] ?? null) ? $post['equipos_foto'] : [],
                $this->usuarioId()
            );
            Session::flash('success', sprintf('%d foto(s) agregada(s) al informe.', $cantidad));
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('informes/' . $id . '#evidencias');
    }

    public function eliminarEvidencia(int $id, int $evidenciaId): void
    {
        try {
            (new InformeService())->eliminarEvidencia($id, $evidenciaId, $this->usuarioId());
            Session::flash('success', 'Foto eliminada.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('informes/' . $id . '#evidencias');
    }

    /** JSON: equipos evaluables para el buscador del formulario. */
    public function buscarEquipos(): void
    {
        $q = mb_substr($this->request->str('q'), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'equipos' => []]);
        }
        $equipos = array_map(
            static fn (array $e): array => $e + ['caracteristicas_sugeridas' => InformeService::caracteristicasSugeridas($e)],
            (new InformeEquipoModel())->buscarEvaluables($q)
        );

        $this->json(['ok' => true, 'equipos' => $equipos]);
    }

    public function verPdf(int $id): void
    {
        $this->enviarPdf($id, true);
    }

    public function descargarPdf(int $id): void
    {
        $this->enviarPdf($id, false);
    }

    public function descargarWord(int $id): void
    {
        try {
            $word = (new InformeService())->word($id);
        } catch (ValidationException $e) {
            Session::flash('warning', $e->getMessage());
            $this->redirect('informes/' . $id);
        }

        Response::download($word['ruta'], $word['nombre'], false, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    private function enviarPdf(int $id, bool $inline): void
    {
        $pdf = (new InformeService())->pdf($id);
        if ($pdf['contenido'] !== null) {
            if (!$inline) {
                Session::flash('warning', 'La descarga del PDF está disponible cuando el informe se emite. Use la vista previa.');
                $this->redirect('informes/' . $id);
            }
            Response::content($pdf['contenido'], $pdf['nombre'], 'application/pdf', true);
        }

        Response::download((string) $pdf['ruta'], $pdf['nombre'], $inline, 'application/pdf');
    }

    /**
     * Filas de equipos del formulario: tras un error se reconstruyen desde lo
     * enviado; si no, desde el borrador o desde los equipos precargados.
     *
     * @param array<int|string, mixed> $idsPrecarga
     * @param list<array<string, mixed>> $guardados
     * @return list<array<string, mixed>>
     */
    private function filasPrecargadas(array $idsPrecarga, array $guardados): array
    {
        $modelo = new InformeEquipoModel();

        if (hay_old()) {
            $ids = array_map('intval', old_array('equipos'));
            $datos = [];
            foreach ($modelo->equiposPorIds($ids) as $eq) {
                $datos[(int) $eq['id']] = $eq;
            }
            $filas = [];
            foreach ($ids as $id) {
                if (!isset($datos[$id])) {
                    continue;
                }
                $filas[] = $datos[$id] + [
                    'equipo_id'        => $id,
                    'caracteristicas'  => (string) (old_array('caracteristicas')[$id] ?? ''),
                    'estado_funcional' => (string) (old_array('estado_funcional')[$id] ?? ''),
                    'diagnostico'      => (string) (old_array('diagnostico')[$id] ?? ''),
                ];
            }

            return $filas;
        }

        if ($guardados !== []) {
            return $guardados;
        }

        return array_map(static fn (array $eq): array => $eq + [
            'equipo_id'        => (int) $eq['id'],
            'caracteristicas'  => InformeService::caracteristicasSugeridas($eq),
            'estado_funcional' => '',
            'diagnostico'      => '',
        ], $modelo->equiposPorIds(array_map('intval', $idsPrecarga)));
    }

    /**
     * @param array<string, mixed>|null $informe
     * @param list<array<string, mixed>> $filas
     * @return array<string, mixed>
     */
    private function datosFormulario(?array $informe, array $filas): array
    {
        return [
            'titulo'       => $informe === null ? 'Nuevo informe técnico' : 'Editar informe (borrador)',
            'informe'      => $informe,
            'filas'        => $filas,
            'defecto'      => (new InformeService())->valoresPorDefecto(),
            'acciones'     => InformeModel::ACCIONES,
            'diagnosticos' => InformeService::DIAGNOSTICOS_RAPIDOS,
        ];
    }
}
