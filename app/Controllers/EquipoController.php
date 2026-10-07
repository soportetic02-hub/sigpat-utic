<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Response;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\EquipoCodigoModel;
use App\Models\EquipoModel;
use App\Models\EquipoPuntoRedModel;
use App\Models\HistorialAsignacionModel;
use App\Models\LicenciaEquipoModel;
use App\Models\PersonalModel;
use App\Services\EquipoService;
use App\Services\OficinaService;

/**
 * Inventario de equipos. ADMINISTRADOR y TECNICO registran y editan;
 * eliminar y confirmar la baja son exclusivos del ADMINISTRADOR.
 */
final class EquipoController extends Controller
{
    public function index(): void
    {
        $filtros = $this->filtros();

        $this->view('equipos/index', [
            'titulo'   => 'Inventario de equipos',
            'pagina'   => (new EquipoModel())->listado($filtros, max(1, $this->request->int('page', 1))),
            'filtros'  => $filtros,
            'enlace'   => $this->filtrosParaEnlace($filtros),
            'oficinas' => (new OficinaService())->opcionesSelect(),
        ]);
    }

    /** CSV del listado filtrado (UTF-8 con BOM para que Excel muestre las tildes). */
    public function exportar(): void
    {
        $filas = (new EquipoModel())->exportar($this->filtros());

        $encabezados = [
            'ID', 'Tipo', 'Marca', 'Modelo', 'N° serie', 'Código patrimonial', 'Código interno', 'Código inventario ' . date('Y'),
            'Procesador', 'RAM (GB)', 'Disco', 'Capacidad (GB)', 'Sistema operativo', 'Hostname', 'IP', 'MAC LAN',
            'Oficina', 'Responsable', 'En uso', 'Estado operativo', 'Condición física', 'Recomendado baja',
            'Fecha adquisición', 'Antigüedad (años)', 'Fecha sugerida baja', 'Último mantenimiento', 'Próximo mantenimiento',
            'Licencia Microsoft 365',
        ];

        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, $encabezados);
        foreach ($filas as $f) {
            $fila = [
                $f['id'], $f['tipo'], $f['marca'], $f['modelo'], $f['nro_serie'], $f['codigo_patrimonial'], $f['codigo_interno'],
                $f['codigo_inventario_anio'], $f['procesador'], $f['ram_gb'], $f['disco_tipo'], $f['disco_capacidad_gb'],
                $f['sistema_operativo'], $f['hostname'], $f['ip_lan'], $f['mac_lan'], $f['oficina_nombre'], $f['personal_nombre'],
                (int) $f['en_uso'] === 1 ? 'Sí' : 'No', $f['estado_operativo'], $f['condicion_fisica'],
                (int) $f['recomendado_baja'] === 1 ? 'Sí' : 'No', fecha($f['fecha_adquisicion']), $f['anios_antiguedad'],
                fecha($f['fecha_sugerida_baja']), fecha($f['fecha_ultimo_mantenimiento']), fecha($f['fecha_proximo_mantenimiento']),
                $f['licencia_id'] !== null ? 'Sí' : 'No',
            ];
            fputcsv($salida, array_map([$this, 'celdaSegura'], $fila));
        }
        rewind($salida);
        $contenido = (string) stream_get_contents($salida);
        fclose($salida);

        Response::content($contenido, 'inventario_equipos_' . date('Ymd_His') . '.csv', 'text/csv; charset=utf-8');
    }

    public function mostrar(int $id): void
    {
        $modelo = new EquipoModel();
        $ficha = $modelo->ficha($id);
        if ($ficha === null) {
            (new EquipoService())->obtener($id);
        }

        $this->view('equipos/show', [
            'titulo'    => 'Ficha del equipo',
            'equipo'    => $ficha,
            'codigos'   => (new EquipoCodigoModel())->porEquipo($id),
            'puntos'    => (new EquipoPuntoRedModel())->porEquipo($id),
            'licencia'  => (new LicenciaEquipoModel())->activoDeEquipo($id),
            'historial' => (new HistorialAsignacionModel())->porEquipo($id),
            'actas'     => $modelo->actas($id),
            'informes'  => $modelo->informes($id),
            'eliminable' => !$modelo->tieneDocumentosRelacionados($id),
        ]);
    }

    public function crear(): void
    {
        $servicio = new EquipoService();
        $oficina = $this->request->int('oficina_id');

        $this->view('equipos/form', $this->datosFormulario(null, [
            'titulo'       => 'Registrar equipo',
            'oficinaId'    => $oficina > 0 ? $oficina : null,
            'personalId'   => null,
            'codigos'      => [],
            'puntos'       => [],
            'vidaUtilDef'  => $servicio->vidaUtilPorDefecto(),
        ]));
    }

    public function guardar(): void
    {
        try {
            $id = (new EquipoService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('equipos/crear', $e->getErrores());
        }

        Session::flash('success', 'Equipo registrado correctamente.');
        $this->redirect('equipos/' . $id);
    }

    public function editar(int $id): void
    {
        $servicio = new EquipoService();
        $equipo = $servicio->obtener($id);
        if ($equipo['estado_operativo'] === 'DE_BAJA') {
            Session::flash('warning', 'Un equipo dado de baja no puede modificarse.');
            $this->redirect('equipos/' . $id);
        }

        $this->view('equipos/form', $this->datosFormulario($equipo, [
            'titulo'      => 'Editar equipo',
            'oficinaId'   => (int) $equipo['oficina_id'],
            'personalId'  => $equipo['personal_id'] === null ? null : (int) $equipo['personal_id'],
            'codigos'     => (new EquipoCodigoModel())->porEquipo($id),
            'puntos'      => (new EquipoPuntoRedModel())->porEquipo($id),
            'vidaUtilDef' => $servicio->vidaUtilPorDefecto(),
        ]));
    }

    public function actualizar(int $id): void
    {
        try {
            (new EquipoService())->actualizar($id, $this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('equipos/' . $id . '/editar', $e->getErrores());
        }

        Session::flash('success', 'Equipo actualizado correctamente.');
        $this->redirect('equipos/' . $id);
    }

    public function eliminar(int $id): void
    {
        try {
            (new EquipoService())->eliminar($id, $this->usuarioId());
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
            $this->redirect('equipos/' . $id);
        }

        Session::flash('success', 'Equipo eliminado del inventario.');
        $this->redirect('equipos');
    }

    public function confirmarBaja(int $id): void
    {
        try {
            (new EquipoService())->confirmarBaja($id, $this->usuarioId(), $this->request->strOrNull('observacion'));
            Session::flash('success', 'Baja definitiva registrada. Se liberó su instalación de Microsoft 365 (si tenía) y se desvinculó al responsable.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('equipos/' . $id);
    }

    /**
     * @param array<string, mixed>|null $equipo
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function datosFormulario(?array $equipo, array $extra): array
    {
        return array_merge([
            'equipo'      => $equipo,
            'oficinas'    => (new OficinaService())->opcionesSelect($equipo === null ? null : (int) $equipo['oficina_id']),
            'personal'    => (new PersonalModel())->activosParaSelect($equipo === null || $equipo['personal_id'] === null ? null : (int) $equipo['personal_id']),
            'tipos'       => EquipoModel::TIPOS,
            'condiciones' => EquipoModel::CONDICIONES,
            'estados'     => EquipoService::ESTADOS_EDITABLES,
            'discos'      => EquipoService::DISCOS,
        ], $extra);
    }

    /**
     * @return array{q: string, tipo: ?string, estado: ?string, condicion: ?string, oficina_id: ?int, recomendado: bool}
     */
    private function filtros(): array
    {
        $tipo = $this->request->str('tipo');
        $estado = $this->request->str('estado');
        $condicion = $this->request->str('condicion');
        $oficina = $this->request->int('oficina_id');

        return [
            'q'           => mb_substr($this->request->str('q'), 0, 100),
            'tipo'        => in_array($tipo, EquipoModel::TIPOS, true) ? $tipo : null,
            'estado'      => in_array($estado, array_merge(EquipoModel::ESTADOS, ['TODOS']), true) ? $estado : null,
            'condicion'   => in_array($condicion, EquipoModel::CONDICIONES, true) ? $condicion : null,
            'oficina_id'  => $oficina > 0 ? $oficina : null,
            'recomendado' => $this->request->bool('recomendado'),
        ];
    }

    /**
     * @param array<string, mixed> $filtros
     * @return array<string, scalar|null>
     */
    private function filtrosParaEnlace(array $filtros): array
    {
        return [
            'q'           => $filtros['q'],
            'tipo'        => $filtros['tipo'],
            'estado'      => $filtros['estado'],
            'condicion'   => $filtros['condicion'],
            'oficina_id'  => $filtros['oficina_id'],
            'recomendado' => $filtros['recomendado'] ? '1' : null,
        ];
    }

    /** Evita la inyección de fórmulas al abrir el CSV en Excel. */
    private function celdaSegura(mixed $valor): string
    {
        $texto = $valor === null ? '' : (string) $valor;

        return $texto !== '' && in_array($texto[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $texto : $texto;
    }
}
