<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\Cifrado;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\EquipoModel;
use App\Models\LicenciaEquipoModel;
use App\Models\LicenciaModel;
use App\Models\OficinaModel;
use App\Models\PersonalModel;
use App\Services\Exceptions\EquipoConLicenciaException;
use App\Services\Exceptions\LicenciaLlenaException;
use DateTimeImmutable;
use Throwable;

/**
 * Cuentas Microsoft 365 y la regla de 5 instalaciones por cuenta (sección 7.2).
 *
 * La regla se garantiza en dos barreras: aquí (transacción + SELECT ... FOR UPDATE
 * sobre la cuenta) y en la BD (CHECK del slot y UNIQUE sobre las columnas
 * generadas slot_activo y equipo_activo). Las violaciones de la BD (SQLSTATE 23000)
 * se traducen a las mismas excepciones de negocio.
 *
 * Las contraseñas de las cuentas se guardan cifradas (AES-256-GCM, APP_KEY) y solo
 * un ADMINISTRADOR puede verlas; cada visualización queda en la auditoría.
 */
final class LicenciaService
{
    public const ESTADOS = ['ACTIVA', 'SUSPENDIDA', 'VENCIDA', 'BAJA'];
    public const VERIFICACION = ['OK', 'POR_VERIFICAR'];
    public const MOTIVOS_LIBERACION = ['FORMATEO', 'BAJA', 'REASIGNACION', 'OTRO'];
    public const TIPOS_ELEGIBLES = ['PC', 'LAPTOP'];
    public const PLAN_DEFECTO = 'Microsoft 365 Empresa Estándar';

    private LicenciaModel $licencias;
    private LicenciaEquipoModel $slots;
    private EquipoModel $equipos;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->licencias = new LicenciaModel();
        $this->slots = new LicenciaEquipoModel();
        $this->equipos = new EquipoModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $licencia = $this->licencias->find($id);
        if ($licencia === null) {
            throw new HttpException(404, 'La cuenta Microsoft 365 no existe.');
        }

        return $licencia;
    }

    /** Capacidad efectiva de una cuenta: max_instalaciones, nunca más de 5. */
    public static function capacidad(array $licencia): int
    {
        return max(1, min((int) ($licencia['max_instalaciones'] ?? LicenciaModel::MAX_SLOTS), LicenciaModel::MAX_SLOTS));
    }

    /**
     * Ocupa una instalación de la cuenta. Usa $slotPreferido si está libre; si no, el primer
     * slot libre. Devuelve el número de slot asignado.
     *
     * $entrada: equipo_id | equipo_texto, personal_id | usuario_texto, oficina_id,
     * estado_verificacion y observacion (ver normalizarInstalacion()).
     *
     * @param array<string, mixed> $entrada
     * @throws LicenciaLlenaException
     * @throws EquipoConLicenciaException
     * @throws ValidationException
     */
    public function asignarSlot(int $licenciaId, array $entrada, int $usuarioId, ?int $slotPreferido = null): int
    {
        $datos = $this->normalizarInstalacion($entrada);

        $this->db->beginTransaction();
        try {
            $slot = $this->ocuparSlot($licenciaId, $datos, $usuarioId, $slotPreferido);
            $this->db->commit();

            return $slot;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirViolacion($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Libera un slot sin borrar la fila (queda en el historial).
     */
    public function liberarSlot(int $licenciaEquipoId, string $motivo, int $usuarioId, ?string $observacion = null): void
    {
        if (!in_array($motivo, self::MOTIVOS_LIBERACION, true)) {
            throw ValidationException::campo('motivo', 'Seleccione un motivo de liberación válido.');
        }
        if ($observacion !== null && mb_strlen($observacion) > 255) {
            throw ValidationException::campo('observacion', 'La observación admite como máximo 255 caracteres.');
        }

        $this->db->beginTransaction();
        try {
            $fila = $this->slots->findForUpdate($licenciaEquipoId);
            if ($fila === null) {
                throw new HttpException(404, 'La instalación no existe.');
            }
            if ($fila['fecha_liberacion'] !== null) {
                throw ValidationException::campo('slot', 'Esa instalación ya había sido liberada.');
            }

            $this->slots->liberar($licenciaEquipoId, $motivo, $usuarioId, $observacion);
            $this->auditoria->registrar(
                AuditoriaService::LIBERAR_SLOT,
                'licencia_equipos',
                $licenciaEquipoId,
                $this->instantaneaSlot($fila),
                ['motivo_liberacion' => $motivo, 'observacion' => $observacion],
                $usuarioId
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Libera el slot (motivo REASIGNACION) y lo ocupa con la nueva instalación en la misma
     * transacción. Devuelve el número de slot.
     *
     * @param array<string, mixed> $entrada
     */
    public function reasignarSlot(int $licenciaEquipoId, array $entrada, int $usuarioId): int
    {
        $datos = $this->normalizarInstalacion($entrada);

        $this->db->beginTransaction();
        try {
            $fila = $this->slots->findForUpdate($licenciaEquipoId);
            if ($fila === null) {
                throw new HttpException(404, 'La instalación no existe.');
            }
            if ($fila['fecha_liberacion'] !== null) {
                throw ValidationException::campo('slot', 'Esa instalación ya había sido liberada.');
            }
            if ($datos['equipo_id'] !== null && (int) $fila['equipo_id'] === $datos['equipo_id']) {
                throw ValidationException::campo('equipo_id', 'El equipo seleccionado ya ocupa este slot.');
            }

            $this->liberarSlot($licenciaEquipoId, 'REASIGNACION', $usuarioId, $datos['observacion']);
            $slot = $this->ocuparSlot((int) $fila['licencia_id'], $datos, $usuarioId, (int) $fila['slot']);
            $this->db->commit();

            return $slot;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirViolacion($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Los slots de una cuenta: cada posición trae la instalación o null si está libre.
     *
     * @return array{licencia: array<string, mixed>, slots: array<int, array<string, mixed>|null>, ocupados: int, capacidad: int}
     */
    public function resumen(int $licenciaId): array
    {
        $licencia = $this->obtener($licenciaId);
        $capacidad = self::capacidad($licencia);
        $slots = array_fill(1, $capacidad, null);
        foreach ($this->slots->activosDeLicencia($licenciaId) as $fila) {
            $slots[(int) $fila['slot']] = $fila;
        }
        ksort($slots);

        return [
            'licencia'  => $licencia,
            'slots'     => $slots,
            'ocupados'  => count(array_filter($slots)),
            'capacidad' => $capacidad,
        ];
    }

    /** @param array<string, mixed> $entrada */
    public function crear(array $entrada, int $usuarioId): int
    {
        [$datos, $contrasena] = $this->validar($entrada, null);
        if ($contrasena !== null) {
            $datos['contrasena_cifrada'] = Cifrado::cifrar($contrasena);
        }
        $datos['max_instalaciones'] = LicenciaModel::MAX_SLOTS;
        $datos['activo'] = 1;
        $datos['created_by'] = $usuarioId;

        $this->db->beginTransaction();
        try {
            $id = $this->licencias->insert($datos);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'licencias_office', $id, null, $this->paraAuditoria($this->licencias->find($id)), $usuarioId);
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirDuplicado($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $entrada */
    public function actualizar(int $id, array $entrada, int $usuarioId): void
    {
        $antes = $this->obtener($id);
        [$datos, $contrasena] = $this->validar($entrada, $id);
        if ($contrasena !== null) {
            $datos['contrasena_cifrada'] = Cifrado::cifrar($contrasena);
        }

        $this->db->beginTransaction();
        try {
            if ($datos['estado'] === 'BAJA' && $this->licencias->contarSlotsActivos($id) > 0) {
                throw ValidationException::campo('estado', 'No se puede dar de baja una cuenta con instalaciones vigentes. Libérelas primero.');
            }
            $this->licencias->update($id, $datos);
            $despues = $this->paraAuditoria($this->licencias->find($id));
            if ($despues !== null && $contrasena !== null) {
                $despues['contrasena'] = 'modificada';
            }
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'licencias_office', $id, $this->paraAuditoria($antes), $despues, $usuarioId);
            $this->db->commit();
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirDuplicado($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** Activa o desactiva una cuenta. Solo se desactiva si no tiene instalaciones vigentes. */
    public function alternarActivo(int $id, int $usuarioId): bool
    {
        $this->db->beginTransaction();
        try {
            $licencia = $this->licencias->findForUpdate($id);
            if ($licencia === null) {
                throw new HttpException(404, 'La cuenta Microsoft 365 no existe.');
            }
            $nuevo = (int) $licencia['activo'] === 1 ? 0 : 1;
            if ($nuevo === 0 && $this->licencias->contarSlotsActivos($id) > 0) {
                throw ValidationException::campo('activo', 'No se puede desactivar una cuenta con instalaciones vigentes. Libérelas primero.');
            }

            $this->licencias->update($id, ['activo' => $nuevo]);
            $this->auditoria->registrar(
                $nuevo === 0 ? AuditoriaService::ELIMINAR : AuditoriaService::EDITAR,
                'licencias_office',
                $id,
                ['activo' => (int) $licencia['activo']],
                ['activo' => $nuevo],
                $usuarioId
            );
            $this->db->commit();

            return $nuevo === 1;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Descifra la contraseña de la cuenta y registra la visualización en la auditoría.
     * Si la auditoría falla, la contraseña no se entrega.
     */
    public function verContrasena(int $id, int $usuarioId): string
    {
        $licencia = $this->obtener($id);
        if ($licencia['contrasena_cifrada'] === null || $licencia['contrasena_cifrada'] === '') {
            throw ValidationException::campo('contrasena', 'Esta cuenta no tiene una contraseña registrada.');
        }

        $contrasena = Cifrado::descifrar((string) $licencia['contrasena_cifrada']);
        $this->auditoria->registrar(
            AuditoriaService::VER_CONTRASENA,
            'licencias_office',
            $id,
            null,
            ['codigo' => $licencia['codigo'], 'correo' => $licencia['correo']],
            $usuarioId
        );

        return $contrasena;
    }

    /**
     * Valida y normaliza los datos de una instalación.
     *
     * @param array<string, mixed> $e
     * @return array{equipo_id: ?int, equipo_texto: ?string, personal_id: ?int, usuario_texto: ?string,
     *               oficina_id: ?int, estado_verificacion: string, observacion: ?string}
     */
    public function normalizarInstalacion(array $e): array
    {
        $txt = static function (string $c, int $max) use ($e): ?string {
            $v = is_scalar($e[$c] ?? null) ? trim((string) preg_replace('/\s+/u', ' ', (string) $e[$c])) : '';

            return $v === '' ? null : mb_substr($v, 0, $max);
        };
        $id = static fn (string $c): ?int => is_scalar($e[$c] ?? null) && ctype_digit((string) $e[$c]) && (int) $e[$c] > 0 ? (int) $e[$c] : null;
        $errores = [];

        $datos = [
            'equipo_id'           => $id('equipo_id'),
            'equipo_texto'        => $txt('equipo_texto', 100),
            'personal_id'         => $id('personal_id'),
            'usuario_texto'       => $txt('usuario_texto', 150),
            'oficina_id'          => $id('oficina_id'),
            'estado_verificacion' => is_scalar($e['estado_verificacion'] ?? null) && in_array($e['estado_verificacion'], self::VERIFICACION, true)
                ? (string) $e['estado_verificacion'] : 'OK',
            'observacion'         => $txt('observacion', 255),
        ];
        // Un dato del inventario o de Personal reemplaza al texto libre.
        if ($datos['equipo_id'] !== null) {
            $datos['equipo_texto'] = null;
        }
        if ($datos['personal_id'] !== null) {
            $datos['usuario_texto'] = null;
        }

        if ($datos['equipo_id'] === null && $datos['equipo_texto'] === null) {
            $errores['equipo'] = 'Seleccione un equipo del inventario o escriba el nombre del equipo.';
        }
        if ($datos['personal_id'] !== null && (new PersonalModel())->find($datos['personal_id']) === null) {
            $errores['personal_id'] = 'La persona seleccionada no existe.';
        }
        if ($datos['oficina_id'] !== null && (new OficinaModel())->find($datos['oficina_id']) === null) {
            $errores['oficina_id'] = 'La oficina seleccionada no existe.';
        }

        if ($errores !== []) {
            throw new ValidationException($errores, reset($errores));
        }

        return $datos;
    }

    /**
     * Debe llamarse dentro de una transacción abierta.
     *
     * @param array{equipo_id: ?int, equipo_texto: ?string, personal_id: ?int, usuario_texto: ?string,
     *              oficina_id: ?int, estado_verificacion: string, observacion: ?string} $datos
     */
    private function ocuparSlot(int $licenciaId, array $datos, int $usuarioId, ?int $slotPreferido): int
    {
        $licencia = $this->licencias->findForUpdate($licenciaId);
        if ($licencia === null) {
            throw new HttpException(404, 'La cuenta Microsoft 365 no existe.');
        }
        if ((int) $licencia['activo'] !== 1) {
            throw ValidationException::campo('licencia', 'La cuenta está desactivada.');
        }
        if ($licencia['estado'] !== 'ACTIVA') {
            throw ValidationException::campo('licencia', 'La cuenta está ' . mb_strtolower(etiqueta((string) $licencia['estado'])) . ': solo una cuenta activa admite nuevas instalaciones.');
        }

        if ($datos['equipo_id'] !== null) {
            $equipo = $this->equipos->findForUpdate($datos['equipo_id']);
            if ($equipo === null) {
                throw ValidationException::campo('equipo_id', 'El equipo seleccionado no existe.');
            }
            if (!in_array($equipo['tipo'], self::TIPOS_ELEGIBLES, true)) {
                throw ValidationException::campo('equipo_id', 'Del inventario solo las PC y laptops pueden recibir una instalación. Para otros dispositivos escriba el nombre del equipo.');
            }
            if ($equipo['estado_operativo'] === 'DE_BAJA') {
                throw ValidationException::campo('equipo_id', 'No se puede asignar una instalación a un equipo dado de baja.');
            }

            $actual = $this->slots->activoDeEquipo($datos['equipo_id'], true);
            if ($actual !== null) {
                throw new EquipoConLicenciaException(sprintf(
                    'El equipo ya ocupa la instalación %d de la cuenta %s (%s). Libérela antes de asignarle otra.',
                    $actual['slot'],
                    $actual['codigo'],
                    $actual['correo']
                ));
            }

            // Por defecto, la oficina y el responsable del equipo en el inventario.
            if ($datos['oficina_id'] === null) {
                $datos['oficina_id'] = (int) $equipo['oficina_id'];
            }
            if ($datos['personal_id'] === null && $datos['usuario_texto'] === null && $equipo['personal_id'] !== null) {
                $datos['personal_id'] = (int) $equipo['personal_id'];
            }
        }

        // Sin oficina ni equipo del inventario: la oficina de la persona registrada.
        if ($datos['oficina_id'] === null && $datos['personal_id'] !== null) {
            $persona = (new PersonalModel())->find($datos['personal_id']);
            $datos['oficina_id'] = $persona === null ? null : (int) $persona['oficina_id'];
        }

        $capacidad = self::capacidad($licencia);
        $ocupados = $this->slots->slotsOcupados($licenciaId, true);
        $slot = null;
        if ($slotPreferido !== null && $slotPreferido >= 1 && $slotPreferido <= $capacidad && !in_array($slotPreferido, $ocupados, true)) {
            $slot = $slotPreferido;
        } else {
            for ($i = 1; $i <= $capacidad; $i++) {
                if (!in_array($i, $ocupados, true)) {
                    $slot = $i;
                    break;
                }
            }
        }
        if ($slot === null) {
            throw new LicenciaLlenaException();
        }

        $fila = $datos + [
            'licencia_id'  => $licenciaId,
            'slot'         => $slot,
            'asignado_por' => $usuarioId,
        ];
        $id = $this->slots->insert($fila);
        $this->auditoria->registrar(AuditoriaService::ASIGNAR_SLOT, 'licencia_equipos', $id, null, $this->instantaneaSlot($fila), $usuarioId);

        return $slot;
    }

    /**
     * @param array<string, mixed> $fila
     * @return array<string, mixed>
     */
    private function instantaneaSlot(array $fila): array
    {
        $claves = ['licencia_id', 'slot', 'equipo_id', 'equipo_texto', 'personal_id', 'usuario_texto', 'oficina_id', 'estado_verificacion', 'observacion'];
        $datos = [];
        foreach ($claves as $c) {
            $v = $fila[$c] ?? null;
            $datos[$c] = in_array($c, ['licencia_id', 'slot', 'equipo_id', 'personal_id', 'oficina_id'], true) && $v !== null ? (int) $v : $v;
        }

        return $datos;
    }

    /** Segunda barrera: traduce las violaciones de la BD a excepciones de negocio. */
    private function traducirViolacion(DatabaseException $e): Throwable
    {
        if (!$e->esViolacionIntegridad()) {
            return $e;
        }

        return match ($e->getConstraint()) {
            'uq_licencia_equipos_equipo_activo' => new EquipoConLicenciaException(),
            'uq_licencia_equipos_slot_activo'   => new LicenciaLlenaException('Ese slot acaba de ser ocupado por otro usuario. Actualice la página e inténtelo de nuevo.'),
            'chk_licencia_equipos_slot'         => new LicenciaLlenaException(),
            'chk_licencia_equipos_equipo'       => ValidationException::campo('equipo', 'Seleccione un equipo del inventario o escriba el nombre del equipo.'),
            default                             => $e,
        };
    }

    private function traducirDuplicado(DatabaseException $e): Throwable
    {
        if (!$e->esDuplicado()) {
            return $e;
        }

        return $e->getConstraint() === 'uq_licencias_office_codigo'
            ? ValidationException::campo('codigo', 'Ya existe una cuenta con ese código.')
            : ValidationException::campo('correo', 'Ya existe una cuenta con ese correo.');
    }

    /**
     * Devuelve [datos para la BD, contraseña en claro o null si no se cambia].
     *
     * @param array<string, mixed> $e
     * @return array{0: array<string, mixed>, 1: ?string}
     */
    private function validar(array $e, ?int $id): array
    {
        $txt = static fn (string $c): string => is_scalar($e[$c] ?? null) ? trim((string) $e[$c]) : '';
        $errores = [];

        $datos = [
            'codigo'            => $txt('codigo'),
            'correo'            => mb_strtolower($txt('correo')),
            'plan'              => $txt('plan') !== '' ? $txt('plan') : self::PLAN_DEFECTO,
            'fecha_alta'        => $txt('fecha_alta') !== '' ? $txt('fecha_alta') : null,
            'fecha_vencimiento' => $txt('fecha_vencimiento') !== '' ? $txt('fecha_vencimiento') : null,
            'estado'            => $txt('estado') !== '' ? $txt('estado') : 'ACTIVA',
            'observaciones'     => $txt('observaciones') !== '' ? $txt('observaciones') : null,
        ];

        if (preg_match('/^[A-Za-z0-9._-]{1,20}$/', $datos['codigo']) !== 1) {
            $errores['codigo'] = 'Ingrese el código (hasta 20 letras, números, punto, guion o guion bajo; p. ej. licencia01).';
        } elseif ($this->licencias->existeValor('codigo', $datos['codigo'], $id)) {
            $errores['codigo'] = 'Ya existe una cuenta con ese código.';
        }

        if ($datos['correo'] === '' || mb_strlen($datos['correo']) > 150 || filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) === false) {
            $errores['correo'] = 'Ingrese un correo válido (p. ej. lic01@caen2023.onmicrosoft.com).';
        } elseif ($this->licencias->existeValor('correo', $datos['correo'], $id)) {
            $errores['correo'] = 'Ya existe una cuenta con ese correo.';
        }

        if (mb_strlen($datos['plan']) > 80) {
            $errores['plan'] = 'El plan admite como máximo 80 caracteres.';
        }
        if (!in_array($datos['estado'], self::ESTADOS, true)) {
            $errores['estado'] = 'Seleccione un estado válido.';
        }

        $fechas = [];
        foreach (['fecha_alta' => 'alta', 'fecha_vencimiento' => 'vencimiento'] as $campo => $nombre) {
            if ($datos[$campo] === null) {
                continue;
            }
            $f = DateTimeImmutable::createFromFormat('!Y-m-d', $datos[$campo]);
            if ($f === false || $f->format('Y-m-d') !== $datos[$campo]) {
                $errores[$campo] = 'Ingrese una fecha de ' . $nombre . ' válida.';
            } else {
                $fechas[$campo] = $f;
            }
        }
        if (isset($fechas['fecha_alta'], $fechas['fecha_vencimiento']) && $fechas['fecha_vencimiento'] < $fechas['fecha_alta']) {
            $errores['fecha_vencimiento'] = 'El vencimiento no puede ser anterior a la fecha de alta.';
        }
        if ($datos['observaciones'] !== null && mb_strlen($datos['observaciones']) > 2000) {
            $errores['observaciones'] = 'Máximo 2000 caracteres.';
        }

        // La contraseña no se recorta: los espacios pueden ser parte de ella. Vacía = no se cambia.
        $contrasena = is_scalar($e['password_cuenta'] ?? null) ? (string) $e['password_cuenta'] : '';
        if ($contrasena !== '' && (mb_strlen($contrasena) > 128 || preg_match('/[\x00-\x1F\x7F]/', $contrasena) === 1)) {
            $errores['password_cuenta'] = 'La contraseña admite hasta 128 caracteres y no puede tener caracteres de control.';
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return [$datos, $contrasena === '' ? null : $contrasena];
    }

    /**
     * La auditoría nunca guarda la contraseña (ni cifrada): solo si está registrada.
     *
     * @param array<string, mixed>|null $fila
     * @return array<string, mixed>|null
     */
    private function paraAuditoria(?array $fila): ?array
    {
        if ($fila === null) {
            return null;
        }
        $fila['contrasena'] = ($fila['contrasena_cifrada'] ?? null) !== null ? 'registrada' : 'sin registrar';
        unset($fila['contrasena_cifrada']);

        return $fila;
    }
}
