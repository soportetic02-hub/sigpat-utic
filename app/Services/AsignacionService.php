<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\EquipoModel;
use App\Models\HistorialAsignacionModel;
use App\Models\OficinaModel;
use App\Models\PersonalModel;
use Throwable;

/**
 * Ubicación (oficina) y responsable (personal) de los equipos (sección 7.1).
 *
 * Todo cambio cierra el registro vigente de historial_asignaciones y abre uno
 * nuevo, junto con la actualización del equipo, en una sola transacción.
 * Si ya existe una transacción abierta (p. ej. una rotación de personal), se
 * integra en ella.
 */
final class AsignacionService
{
    public const MOTIVO_ALTA = 'ALTA';
    public const MOTIVO_ASIGNACION = 'ASIGNACION';
    public const MOTIVO_DESVINCULACION = 'DESVINCULACION';
    public const MOTIVO_TRASLADO = 'TRASLADO';
    public const MOTIVO_ROTACION = 'ROTACION';
    public const MOTIVO_BAJA = 'BAJA';

    private const MOTIVOS = [
        self::MOTIVO_ALTA, self::MOTIVO_ASIGNACION, self::MOTIVO_DESVINCULACION,
        self::MOTIVO_TRASLADO, self::MOTIVO_ROTACION, self::MOTIVO_BAJA,
    ];

    private Database $db;
    private EquipoModel $equipos;
    private HistorialAsignacionModel $historial;
    private OficinaModel $oficinas;
    private PersonalModel $personal;
    private AuditoriaService $auditoria;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->equipos = new EquipoModel();
        $this->historial = new HistorialAsignacionModel();
        $this->oficinas = new OficinaModel();
        $this->personal = new PersonalModel();
        $this->auditoria = new AuditoriaService();
    }

    /**
     * Cambia la ubicación y/o el responsable de un equipo.
     * Devuelve false si no hubo cambios (no se registra historial), salvo que
     * $forzarRegistro sea true (p. ej. la baja siempre deja constancia).
     */
    public function asignar(
        int $equipoId,
        int $oficinaId,
        ?int $personalId,
        string $motivo,
        int $usuarioId,
        ?string $observacion = null,
        bool $forzarRegistro = false
    ): bool {
        if (!in_array($motivo, self::MOTIVOS, true)) {
            throw new \InvalidArgumentException('Motivo de asignación no válido: ' . $motivo);
        }

        $this->db->beginTransaction();
        try {
            $equipo = $this->equipos->findForUpdate($equipoId);
            if ($equipo === null) {
                throw new HttpException(404, 'El equipo no existe.');
            }
            if ($equipo['estado_operativo'] === 'DE_BAJA' && $motivo !== self::MOTIVO_BAJA) {
                throw ValidationException::campo('equipo_id', 'No se puede asignar un equipo dado de baja.');
            }

            $oficina = $this->oficinas->find($oficinaId);
            if ($oficina === null || ((int) $oficina['activo'] !== 1 && $motivo !== self::MOTIVO_BAJA)) {
                throw ValidationException::campo('oficina_id', 'La oficina de destino no existe o está inactiva.');
            }
            if ($personalId !== null) {
                $persona = $this->personal->find($personalId);
                if ($persona === null || (int) $persona['activo'] !== 1) {
                    throw ValidationException::campo('personal_id', 'El responsable no existe o está inactivo.');
                }
            }

            $actualPersonal = $equipo['personal_id'] === null ? null : (int) $equipo['personal_id'];
            if (!$forzarRegistro && (int) $equipo['oficina_id'] === $oficinaId && $actualPersonal === $personalId
                && $this->historial->vigente($equipoId) !== null) {
                $this->db->commit();

                return false;
            }

            $this->historial->cerrarVigente($equipoId);
            $this->historial->insert([
                'equipo_id'   => $equipoId,
                'oficina_id'  => $oficinaId,
                'personal_id' => $personalId,
                'motivo'      => $motivo,
                'observacion' => $observacion !== null ? mb_substr($observacion, 0, 255) : null,
                'usuario_id'  => $usuarioId,
            ]);
            $this->equipos->actualizarAsignacion($equipoId, $oficinaId, $personalId, $usuarioId);

            $this->auditoria->registrar(
                AuditoriaService::ASIGNAR,
                'equipos',
                $equipoId,
                ['oficina_id' => (int) $equipo['oficina_id'], 'personal_id' => $actualPersonal],
                ['oficina_id' => $oficinaId, 'personal_id' => $personalId, 'motivo' => $motivo, 'observacion' => $observacion],
                $usuarioId
            );

            $this->db->commit();

            return true;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Asigna un equipo a una persona. Si $trasladarAOficinaPersona es true, el
     * equipo pasa a la oficina de la persona; si no, conserva su ubicación.
     */
    public function asignarAPersonal(int $equipoId, int $personalId, int $usuarioId, bool $trasladarAOficinaPersona = true, ?string $observacion = null): bool
    {
        $persona = $this->personal->find($personalId);
        if ($persona === null) {
            throw new HttpException(404, 'La persona no existe.');
        }
        $equipo = $this->equipos->find($equipoId);
        if ($equipo === null) {
            throw new HttpException(404, 'El equipo no existe.');
        }

        $oficinaId = $trasladarAOficinaPersona ? (int) $persona['oficina_id'] : (int) $equipo['oficina_id'];

        return $this->asignar($equipoId, $oficinaId, $personalId, self::MOTIVO_ASIGNACION, $usuarioId, $observacion);
    }

    /** Quita el responsable; el equipo permanece en su oficina actual. */
    public function desvincular(int $equipoId, int $usuarioId, ?string $observacion = null): bool
    {
        $equipo = $this->equipos->find($equipoId);
        if ($equipo === null) {
            throw new HttpException(404, 'El equipo no existe.');
        }
        if ($equipo['personal_id'] === null) {
            throw ValidationException::campo('equipo_id', 'El equipo no tiene responsable asignado.');
        }

        return $this->asignar($equipoId, (int) $equipo['oficina_id'], null, self::MOTIVO_DESVINCULACION, $usuarioId, $observacion);
    }

    /** Traslada un equipo a otra oficina conservando (o no) a su responsable. */
    public function trasladar(int $equipoId, int $oficinaId, int $usuarioId, bool $conservarResponsable = true, ?string $observacion = null): bool
    {
        $equipo = $this->equipos->find($equipoId);
        if ($equipo === null) {
            throw new HttpException(404, 'El equipo no existe.');
        }
        $personalId = $conservarResponsable && $equipo['personal_id'] !== null ? (int) $equipo['personal_id'] : null;

        return $this->asignar($equipoId, $oficinaId, $personalId, self::MOTIVO_TRASLADO, $usuarioId, $observacion);
    }

    /**
     * Abre el primer registro de historial de un equipo recién creado.
     * Debe llamarse dentro de la transacción de alta del equipo.
     */
    public function registrarAlta(int $equipoId, int $oficinaId, ?int $personalId, int $usuarioId): void
    {
        $this->db->beginTransaction();
        try {
            $this->historial->insert([
                'equipo_id'   => $equipoId,
                'oficina_id'  => $oficinaId,
                'personal_id' => $personalId,
                'motivo'      => self::MOTIVO_ALTA,
                'observacion' => 'Alta del equipo en el inventario',
                'usuario_id'  => $usuarioId,
            ]);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}
