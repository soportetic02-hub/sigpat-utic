<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\CatalogoSoftwareModel;
use App\Models\ChecklistItemModel;
use App\Models\EquipoModel;
use App\Models\LicenciaEquipoModel;
use App\Models\MantenimientoDetalleModel;
use App\Models\MantenimientoEnvioModel;
use App\Models\MantenimientoModel;
use App\Models\UsuarioSistemaModel;
use App\Services\ActaEnvioService;
use App\Services\ActaFirmaService;
use App\Services\CorreoService;
use App\Services\MantenimientoService;
use App\Services\OficinaService;

/**
 * Actas de mantenimiento preventivo/correctivo (ADMINISTRADOR y TECNICO).
 */
final class MantenimientoController extends Controller
{
    public function index(): void
    {
        $filtros = $this->filtros();
        $modelo = new MantenimientoModel();

        $this->view('mantenimientos/index', [
            'titulo'   => 'Actas de mantenimiento',
            'pagina'   => $modelo->listado($filtros, max(1, $this->request->int('page', 1))),
            'filtros'  => $filtros,
            'tecnicos' => $modelo->tecnicosConActas(),
            'oficinas' => (new OficinaService())->opcionesSelect(),
            'requiereFirma' => (new ActaEnvioService())->requiereFirma(),
            'esAdmin'  => Auth::esAdministrador(),
        ]);
    }

    /**
     * Paso 1 (elegir personal y equipo) o paso 2 (formulario) si llega equipo_id.
     */
    public function crear(): void
    {
        $equipoId = $this->request->int('equipo_id');
        if ($equipoId <= 0) {
            $this->view('mantenimientos/crear', ['titulo' => 'Nueva acta de mantenimiento']);

            return;
        }

        $servicio = new MantenimientoService();
        try {
            $equipo = $servicio->equipoParaActa($equipoId);
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('mantenimientos/crear');
        }
        $borrador = (new MantenimientoModel())->borradorDeEquipo($equipoId);
        if ($borrador !== null) {
            Session::flash('info', 'Este equipo ya tiene un acta en borrador. Continúe con ella.');
            $this->redirect('mantenimientos/' . $borrador . '/editar');
        }

        $this->view('mantenimientos/form', $this->datosFormulario($equipo, null, [
            'titulo'      => 'Nueva acta de mantenimiento',
            'checklistSel' => [],
            'componentes' => [],
            'softwareSel' => [],
        ]));
    }

    public function guardar(): void
    {
        $equipoId = $this->request->int('equipo_id');
        try {
            $id = (new MantenimientoService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('mantenimientos/crear?equipo_id=' . $equipoId, $e->getErrores(), $e->getMessage());
        }

        $cerrada = ($this->request->str('accion') === 'cerrar');
        Session::flash('success', $cerrada ? 'Acta cerrada y numerada. El PDF está disponible.' : 'Acta guardada como borrador. El equipo quedó EN MANTENIMIENTO.');
        $this->redirect('mantenimientos/' . $id);
    }

    public function mostrar(int $id): void
    {
        $datos = (new MantenimientoService())->obtenerCompleta($id);
        $envio = new ActaEnvioService();

        $this->view('mantenimientos/show', array_merge($datos, [
            'titulo'    => 'Acta de mantenimiento',
            'puedeAnular' => $datos['acta']['estado'] === 'BORRADOR'
                && ((int) $datos['acta']['created_by'] === Auth::id() || Auth::esAdministrador()),
            'puedeEliminar'     => Auth::esAdministrador() && $datos['acta']['estado'] === 'CERRADA'
                && $datos['acta']['estado_envio'] !== 'RECIBIDO',
            'puedeFirmar'       => ActaFirmaService::puedeFirmar(Auth::user()),
            'requiereFirma'     => $envio->requiereFirma(),
            'correoJefa'        => $envio->correoJefa(),
            'correoConfigurado' => CorreoService::configurado(),
            'envios'            => (new MantenimientoEnvioModel())->deActa($id),
        ]));
    }

    /** Sube el PDF firmado digitalmente (ReFirma PDF) por el Jefe de la OTIC. */
    public function firmar(int $id): void
    {
        try {
            (new ActaFirmaService())->firmarConArchivo($id, $this->request->file('pdf_firmado'), $this->usuarioId());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('mantenimientos/' . $id . '#firma');
        }

        Session::flash('success', 'Firma digital verificada. El acta quedó firmada y lista para enviarse.');
        $this->redirect('mantenimientos/' . $id);
    }

    /** Envía el acta por correo al usuario (o al correo indicado). */
    public function enviar(int $id): void
    {
        $error = null;
        try {
            $ok = (new ActaEnvioService())->enviar($id, $this->request->str('destinatario'), $this->usuarioId(), $error);
        } catch (ValidationException $e) {
            $this->volverConErrores('mantenimientos/' . $id . '#envio', $e->getErrores(), $e->getMessage());
        }

        if ($ok) {
            Session::flash('success', 'Acta enviada por correo. Su estado cambiará a RECIBIDO cuando el usuario confirme la recepción.');
        } else {
            Session::flash('danger', 'No se pudo enviar el correo. ' . $error);
        }
        $this->redirect('mantenimientos/' . $id . '#envio');
    }

    public function editar(int $id): void
    {
        $servicio = new MantenimientoService();
        $datos = $servicio->obtenerCompleta($id);
        $acta = $datos['acta'];
        if ($acta['estado'] !== 'BORRADOR') {
            Session::flash('warning', 'El acta está cerrada y no puede modificarse.');
            $this->redirect('mantenimientos/' . $id);
        }

        $marcados = [];
        foreach ($datos['checklist'] as $items) {
            foreach ($items as $i) {
                if ((int) $i['realizado'] === 1) {
                    $marcados[] = (int) $i['checklist_item_id'];
                }
            }
        }
        $softwareSel = [];
        foreach ($datos['software'] as $s) {
            $softwareSel[(int) $s['software_id']] = (string) ($s['version'] ?? '');
        }

        $this->view('mantenimientos/form', $this->datosFormulario(
            (new EquipoModel())->ficha((int) $acta['equipo_id']) ?? [],
            $acta,
            [
                'titulo'       => 'Editar acta (borrador)',
                'checklistSel' => $marcados,
                'componentes'  => $datos['componentes'],
                'softwareSel'  => $softwareSel,
            ]
        ));
    }

    public function actualizar(int $id): void
    {
        try {
            (new MantenimientoService())->actualizar($id, $this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('mantenimientos/' . $id . '/editar', $e->getErrores(), $e->getMessage());
        }

        $cerrada = ($this->request->str('accion') === 'cerrar');
        Session::flash('success', $cerrada ? 'Acta cerrada y numerada. El PDF está disponible.' : 'Borrador actualizado.');
        $this->redirect('mantenimientos/' . $id);
    }

    public function anular(int $id): void
    {
        try {
            (new MantenimientoService())->anular($id, $this->usuarioId());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('mantenimientos/' . $id);
        }

        Session::flash('success', 'Borrador anulado. El equipo volvió a su estado anterior.');
        $this->redirect('mantenimientos');
    }

    /** Elimina un acta no recibida (solo ADMINISTRADOR; la ruta y el servicio lo exigen). */
    public function eliminar(int $id): void
    {
        $numero = (new MantenimientoModel())->find($id)['numero'] ?? null;
        try {
            (new MantenimientoService())->eliminar($id, $this->usuarioId());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('mantenimientos/' . $id);
        }

        Session::flash('success', $numero !== null ? 'Acta N° ' . $numero . ' eliminada.' : 'Acta eliminada.');
        $this->redirect('mantenimientos');
    }

    /** Vista previa del PDF en el navegador. */
    public function verPdf(int $id): void
    {
        $this->enviarPdf($id, true);
    }

    public function descargarPdf(int $id): void
    {
        $this->enviarPdf($id, false);
    }

    /** JSON: equipos a cargo de una persona (paso 1). */
    public function equiposDePersonal(int $id): void
    {
        $equipos = array_values(array_filter(
            (new EquipoModel())->porPersonal($id),
            static fn (array $e): bool => $e['estado_operativo'] !== 'DE_BAJA'
        ));
        $modelo = new MantenimientoModel();
        foreach ($equipos as &$e) {
            $e['borrador_id'] = $modelo->borradorDeEquipo((int) $e['id']);
        }
        unset($e);

        $this->json(['ok' => true, 'equipos' => $equipos]);
    }

    private function enviarPdf(int $id, bool $inline): void
    {
        try {
            $pdf = (new MantenimientoService())->pdf($id);
        } catch (ValidationException $e) {
            Session::flash('warning', $e->getMessage());
            $this->redirect('mantenimientos/' . $id);
        }

        Response::download($pdf['ruta'], $pdf['nombre'], $inline, 'application/pdf');
    }

    /**
     * @param array<string, mixed> $equipo ficha del equipo (v_equipos_estado)
     * @param array<string, mixed>|null $acta
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function datosFormulario(array $equipo, ?array $acta, array $extra): array
    {
        $softwareGuardado = array_keys($extra['softwareSel']);
        $licencia = (new LicenciaEquipoModel())->activoDeEquipo((int) $equipo['id']);

        return array_merge([
            'equipo'        => $equipo,
            'acta'          => $acta,
            'checklist'     => (new ChecklistItemModel())->activosPorTipo((string) $equipo['tipo']),
            'catalogo'      => $equipo['tipo'] === 'IMPRESORA' ? [] : (new CatalogoSoftwareModel())->activos($softwareGuardado),
            'licencia'      => $licencia,
            'tecnicos'      => Auth::esAdministrador() ? (new UsuarioSistemaModel())->activosParaSelect($acta === null ? null : (int) $acta['tecnico_id']) : [],
            'tipos'         => MantenimientoModel::TIPOS,
            'listaComponentes' => MantenimientoDetalleModel::COMPONENTES,
            'acciones'      => MantenimientoDetalleModel::ACCIONES,
            'estadosFinales' => MantenimientoService::ESTADOS_FINALES,
            'condiciones'   => EquipoModel::CONDICIONES,
        ], $extra);
    }

    /**
     * @return array{desde: ?string, hasta: ?string, tecnico_id: ?int, oficina_id: ?int, tipo: ?string, estado: ?string, firma: ?string, envio: ?string, q: string}
     */
    private function filtros(): array
    {
        $fecha = static fn (string $v): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) === 1 ? $v : null;
        $tipo = $this->request->str('tipo');
        $estado = $this->request->str('estado');
        $firma = $this->request->str('firma');
        $envio = $this->request->str('envio');
        $tecnico = $this->request->int('tecnico_id');
        $oficina = $this->request->int('oficina_id');

        return [
            'desde'      => $fecha($this->request->str('desde')),
            'hasta'      => $fecha($this->request->str('hasta')),
            'tecnico_id' => $tecnico > 0 ? $tecnico : null,
            'oficina_id' => $oficina > 0 ? $oficina : null,
            'tipo'       => in_array($tipo, MantenimientoModel::TIPOS, true) ? $tipo : null,
            'estado'     => in_array($estado, MantenimientoModel::ESTADOS, true) ? $estado : null,
            'firma'      => in_array($firma, [MantenimientoModel::FIRMA_FIRMADA, MantenimientoModel::FIRMA_PENDIENTE], true) ? $firma : null,
            'envio'      => in_array($envio, MantenimientoModel::ESTADOS_ENVIO, true) ? $envio : null,
            'q'          => mb_substr($this->request->str('q'), 0, 60),
        ];
    }
}
