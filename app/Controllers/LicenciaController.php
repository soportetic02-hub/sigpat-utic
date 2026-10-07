<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\CifradoException;
use App\Core\Controller;
use App\Core\Logger;
use App\Core\Session;
use App\Core\ValidationException;
use App\Models\LicenciaEquipoModel;
use App\Models\LicenciaModel;
use App\Services\Exceptions\EquipoConLicenciaException;
use App\Services\Exceptions\LicenciaLlenaException;
use App\Services\LicenciaService;
use App\Services\OficinaService;

/**
 * Cuentas Microsoft 365. Crear/editar/desactivar y ver contraseñas: solo ADMINISTRADOR.
 * Ver, asignar, liberar y reasignar instalaciones: ADMINISTRADOR y TECNICO.
 */
final class LicenciaController extends Controller
{
    public function index(): void
    {
        $q = mb_substr($this->request->str('q'), 0, 60);
        $estado = $this->request->str('estado');
        if (!in_array($estado, [...LicenciaService::ESTADOS, 'DESACTIVADAS'], true)) {
            $estado = '';
        }
        $oficinaId = $this->request->int('oficina_id');
        $oficinaId = $oficinaId > 0 ? $oficinaId : null;
        $cupo = $this->request->bool('cupo');
        $modelo = new LicenciaModel();

        $this->view('licencias/index', [
            'titulo'   => 'Licencias Microsoft 365',
            'pagina'   => $modelo->listado($q, $estado, $oficinaId, $cupo, max(1, $this->request->int('page', 1))),
            'totales'  => $modelo->totales(),
            'oficinas' => (new OficinaService())->opcionesSelect(),
            'estados'  => LicenciaService::ESTADOS,
            'filtros'  => ['q' => $q, 'estado' => $estado, 'oficina_id' => $oficinaId, 'cupo' => $cupo ? '1' : null],
        ]);
    }

    public function mostrar(int $id): void
    {
        $resumen = (new LicenciaService())->resumen($id);

        $this->view('licencias/show', [
            'titulo'    => 'Cuenta Microsoft 365',
            'licencia'  => $resumen['licencia'],
            'slots'     => $resumen['slots'],
            'ocupados'  => $resumen['ocupados'],
            'capacidad' => $resumen['capacidad'],
            'liberados' => (new LicenciaEquipoModel())->liberadosDeLicencia($id),
            'motivos'   => LicenciaService::MOTIVOS_LIBERACION,
            'oficinas'  => (new OficinaService())->opcionesSelect(),
        ]);
    }

    public function crear(): void
    {
        $this->view('licencias/form', [
            'titulo'   => 'Registrar cuenta Microsoft 365',
            'licencia' => null,
            'estados'  => LicenciaService::ESTADOS,
        ]);
    }

    public function guardar(): void
    {
        try {
            $id = (new LicenciaService())->crear($this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('licencias/crear', $e->getErrores());
        } catch (CifradoException $e) {
            Logger::exception($e, 'No se pudo cifrar la contraseña de una cuenta nueva');
            $this->volverConErrores('licencias/crear', ['password_cuenta' => 'No se pudo cifrar la contraseña: falta configurar APP_KEY en el .env.']);
        }

        Session::flash('success', 'Cuenta registrada correctamente.');
        $this->redirect('licencias/' . $id);
    }

    public function editar(int $id): void
    {
        $this->view('licencias/form', [
            'titulo'   => 'Editar cuenta Microsoft 365',
            'licencia' => (new LicenciaService())->obtener($id),
            'estados'  => LicenciaService::ESTADOS,
        ]);
    }

    public function actualizar(int $id): void
    {
        try {
            (new LicenciaService())->actualizar($id, $this->request->post(), $this->usuarioId());
        } catch (ValidationException $e) {
            $this->volverConErrores('licencias/' . $id . '/editar', $e->getErrores());
        } catch (CifradoException $e) {
            Logger::exception($e, 'No se pudo cifrar la contraseña de la cuenta #' . $id);
            $this->volverConErrores('licencias/' . $id . '/editar', ['password_cuenta' => 'No se pudo cifrar la contraseña: falta configurar APP_KEY en el .env.']);
        }

        Session::flash('success', 'Cuenta actualizada correctamente.');
        $this->redirect('licencias/' . $id);
    }

    public function alternarActivo(int $id): void
    {
        try {
            $activa = (new LicenciaService())->alternarActivo($id, $this->usuarioId());
            Session::flash('success', $activa ? 'Cuenta activada.' : 'Cuenta desactivada.');
        } catch (ValidationException $e) {
            Session::flash('danger', $e->getMessage());
        }

        $this->redirect('licencias/' . $id);
    }

    /** JSON (solo ADMINISTRADOR): descifra la contraseña; la visualización queda en la auditoría. */
    public function contrasena(int $id): void
    {
        try {
            $contrasena = (new LicenciaService())->verContrasena($id, $this->usuarioId());
        } catch (ValidationException $e) {
            $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (CifradoException $e) {
            Logger::exception($e, 'No se pudo descifrar la contraseña de la cuenta #' . $id);
            $this->json(['ok' => false, 'mensaje' => 'No se pudo descifrar la contraseña. Verifique la APP_KEY del .env.'], 500);
        }

        $this->json(['ok' => true, 'contrasena' => $contrasena]);
    }

    /** JSON: equipos elegibles (PC/LAPTOP, no de baja, sin instalación vigente). */
    public function equiposElegibles(int $id): void
    {
        (new LicenciaService())->obtener($id);
        $q = mb_substr($this->request->str('q'), 0, 60);
        if (mb_strlen($q) < 2) {
            $this->json(['ok' => true, 'equipos' => []]);
        }

        $this->json(['ok' => true, 'equipos' => (new LicenciaEquipoModel())->buscarElegibles($q)]);
    }

    /** Ocupa un slot. Responde JSON a peticiones AJAX; si no, redirige con mensaje. */
    public function asignar(int $id): void
    {
        $slot = $this->request->int('slot');

        $this->ejecutarSlot($id, function () use ($id, $slot): string {
            $asignado = (new LicenciaService())->asignarSlot($id, $this->entradaInstalacion(), $this->usuarioId(), $slot > 0 ? $slot : null);

            return sprintf('Instalación registrada en el slot %d.', $asignado);
        });
    }

    public function liberar(int $id, int $slotId): void
    {
        $this->ejecutarSlot($id, function () use ($id, $slotId): string {
            $this->verificarSlotDeLicencia($id, $slotId);
            (new LicenciaService())->liberarSlot(
                $slotId,
                $this->request->str('motivo'),
                $this->usuarioId(),
                $this->request->strOrNull('observacion')
            );

            return 'Instalación liberada. Queda registrada en el historial de la cuenta.';
        });
    }

    public function reasignar(int $id, int $slotId): void
    {
        $this->ejecutarSlot($id, function () use ($id, $slotId): string {
            $this->verificarSlotDeLicencia($id, $slotId);
            $slot = (new LicenciaService())->reasignarSlot($slotId, $this->entradaInstalacion(), $this->usuarioId());

            return sprintf('Slot %d reasignado.', $slot);
        });
    }

    /** @return array<string, string> */
    private function entradaInstalacion(): array
    {
        $entrada = [];
        foreach (['equipo_id', 'equipo_texto', 'personal_id', 'usuario_texto', 'oficina_id', 'estado_verificacion', 'observacion'] as $campo) {
            $entrada[$campo] = $this->request->str($campo);
        }

        return $entrada;
    }

    /**
     * Ejecuta una operación de slot y responde según el tipo de petición.
     *
     * @param callable(): string $operacion
     */
    private function ejecutarSlot(int $licenciaId, callable $operacion): void
    {
        $ajax = $this->request->isAjax();
        try {
            $mensaje = $operacion();
        } catch (ValidationException | LicenciaLlenaException | EquipoConLicenciaException $e) {
            if ($ajax) {
                $this->json(['ok' => false, 'mensaje' => $e->getMessage()], 422);
            }
            Session::flash('danger', $e->getMessage());
            $this->redirect('licencias/' . $licenciaId);
        }

        Session::flash('success', $mensaje);
        if ($ajax) {
            $this->json(['ok' => true, 'mensaje' => $mensaje]);
        }
        $this->redirect('licencias/' . $licenciaId);
    }

    private function verificarSlotDeLicencia(int $licenciaId, int $slotId): void
    {
        $fila = (new LicenciaEquipoModel())->find($slotId);
        if ($fila === null || (int) $fila['licencia_id'] !== $licenciaId) {
            throw ValidationException::campo('slot', 'La instalación no pertenece a esta cuenta.');
        }
    }
}
