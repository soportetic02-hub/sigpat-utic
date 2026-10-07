<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\EquipoModel;
use App\Models\OficinaModel;
use App\Models\PersonalModel;
use Throwable;

/**
 * Personal institucional: alta, edición, rotación entre oficinas y borrado lógico.
 */
final class PersonalService
{
    public const ROTACION_TRASLADAR = 'TRASLADAR';
    public const ROTACION_DEJAR = 'DEJAR';

    private PersonalModel $personal;
    private OficinaModel $oficinas;
    private EquipoModel $equipos;
    private AsignacionService $asignacion;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->personal = new PersonalModel();
        $this->oficinas = new OficinaModel();
        $this->equipos = new EquipoModel();
        $this->asignacion = new AsignacionService();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $persona = $this->personal->detalle($id);
        if ($persona === null) {
            throw new HttpException(404, 'La persona no existe.');
        }

        return $persona;
    }

    /** @param array<string, mixed> $entrada */
    public function crear(array $entrada, int $usuarioId): int
    {
        $datos = $this->validar($entrada, null, null);
        $datos['activo'] = 1;

        $this->db->beginTransaction();
        try {
            $id = $this->personal->insert($datos);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'personal', $id, null, $this->personal->find($id), $usuarioId);
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Actualiza los datos de la persona. Si cambia de oficina y tiene equipos a
     * su cargo, $decisiones debe indicar para cada equipo TRASLADAR (se muda con
     * la persona) o DEJAR (queda en su oficina actual sin responsable).
     * Todo se procesa en una sola transacción.
     *
     * @param array<string, mixed> $entrada
     * @param array<int|string, mixed> $decisiones equipo_id => TRASLADAR|DEJAR
     * @return array{trasladados: int, liberados: int}
     */
    public function actualizar(int $id, array $entrada, array $decisiones, int $usuarioId): array
    {
        $resumen = ['trasladados' => 0, 'liberados' => 0];

        $this->db->beginTransaction();
        try {
            $antes = $this->personal->findForUpdate($id);
            if ($antes === null) {
                throw new HttpException(404, 'La persona no existe.');
            }
            $oficinaAnterior = (int) $antes['oficina_id'];
            $datos = $this->validar($entrada, $id, $oficinaAnterior);
            $cambiaOficina = $datos['oficina_id'] !== $oficinaAnterior;

            if ($cambiaOficina && (int) $antes['activo'] !== 1) {
                throw ValidationException::campo('oficina_id', 'Active a la persona antes de cambiarla de oficina.');
            }

            $equiposACargo = $cambiaOficina ? $this->equipos->porPersonal($id, true) : [];
            $pendientes = [];
            foreach ($equiposACargo as $equipo) {
                $decision = $decisiones[$equipo['id']] ?? $decisiones[(string) $equipo['id']] ?? null;
                if (!in_array($decision, [self::ROTACION_TRASLADAR, self::ROTACION_DEJAR], true)) {
                    $pendientes[] = $equipo['tipo'] . ' ' . $equipo['marca'] . ' ' . $equipo['modelo'];
                }
            }
            if ($pendientes !== []) {
                throw ValidationException::campo(
                    'rotacion',
                    'La persona tiene equipos a su cargo. Indique qué hacer con cada uno: ' . implode('; ', $pendientes) . '.'
                );
            }

            $this->personal->update($id, $datos);

            $oficinaNueva = $this->oficinas->find($datos['oficina_id']);
            $observacion = sprintf(
                'Rotación de personal: %s %s pasa a %s',
                $datos['nombres'],
                $datos['apellidos'],
                (string) ($oficinaNueva['nombre'] ?? '')
            );
            foreach ($equiposACargo as $equipo) {
                $equipoId = (int) $equipo['id'];
                $decision = $decisiones[$equipoId] ?? $decisiones[(string) $equipoId];
                if ($decision === self::ROTACION_TRASLADAR) {
                    $this->asignacion->asignar($equipoId, $datos['oficina_id'], $id, AsignacionService::MOTIVO_ROTACION, $usuarioId, $observacion);
                    $resumen['trasladados']++;
                } else {
                    $this->asignacion->asignar($equipoId, (int) $equipo['oficina_id'], null, AsignacionService::MOTIVO_DESVINCULACION, $usuarioId, $observacion . ' (el equipo queda en su oficina sin responsable)');
                    $resumen['liberados']++;
                }
            }

            $this->auditoria->registrar(AuditoriaService::EDITAR, 'personal', $id, $antes, $this->personal->find($id), $usuarioId);
            $this->db->commit();

            return $resumen;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $this->traducirError($e);
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Activa o desactiva (borrado lógico). No se desactiva a quien tiene equipos a su cargo.
     */
    public function alternarActivo(int $id, int $usuarioId): bool
    {
        $this->db->beginTransaction();
        try {
            $persona = $this->personal->findForUpdate($id);
            if ($persona === null) {
                throw new HttpException(404, 'La persona no existe.');
            }
            $nuevo = (int) $persona['activo'] === 1 ? 0 : 1;

            if ($nuevo === 0) {
                $equipos = count($this->equipos->porPersonal($id));
                if ($equipos > 0) {
                    throw ValidationException::campo('activo', sprintf(
                        'No se puede desactivar: tiene %d equipo(s) a su cargo. Desvincúlelos o reasígnelos primero.',
                        $equipos
                    ));
                }
            } else {
                $oficina = $this->oficinas->find((int) $persona['oficina_id']);
                if ($oficina === null || (int) $oficina['activo'] !== 1) {
                    throw ValidationException::campo('activo', 'Su oficina está inactiva. Edite a la persona y asígnele una oficina activa.');
                }
            }

            $this->personal->update($id, ['activo' => $nuevo]);
            $nombre = $persona['nombres'] . ' ' . $persona['apellidos'];
            $this->auditoria->registrar(
                $nuevo === 0 ? AuditoriaService::ELIMINAR : AuditoriaService::EDITAR,
                'personal',
                $id,
                ['nombre' => $nombre, 'activo' => (int) $persona['activo']],
                ['nombre' => $nombre, 'activo' => $nuevo],
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
     * @param array<string, mixed> $entrada
     * @return array<string, mixed>
     */
    private function validar(array $entrada, ?int $id, ?int $oficinaActual): array
    {
        $texto = static fn (string $c): string => trim(preg_replace('/\s+/u', ' ', (string) ($entrada[$c] ?? '')) ?? '');
        $oficinaTexto = $texto('oficina_id');

        $datos = [
            'nombres'    => $texto('nombres'),
            'apellidos'  => $texto('apellidos'),
            'dni'        => $texto('dni') !== '' ? $texto('dni') : null,
            'cargo'      => $texto('cargo') !== '' ? $texto('cargo') : null,
            'email'      => $texto('email') !== '' ? mb_strtolower($texto('email')) : null,
            'telefono'   => $texto('telefono') !== '' ? $texto('telefono') : null,
            'oficina_id' => ctype_digit($oficinaTexto) ? (int) $oficinaTexto : 0,
        ];

        $errores = [];
        if ($datos['nombres'] === '' || mb_strlen($datos['nombres']) > 100) {
            $errores['nombres'] = 'Ingrese los nombres (máximo 100 caracteres).';
        }
        if ($datos['apellidos'] === '' || mb_strlen($datos['apellidos']) > 100) {
            $errores['apellidos'] = 'Ingrese los apellidos (máximo 100 caracteres).';
        }
        if ($datos['dni'] !== null) {
            if (preg_match('/^[0-9]{8}$/', $datos['dni']) !== 1) {
                $errores['dni'] = 'El DNI debe tener 8 dígitos.';
            } elseif ($this->personal->existeValor('dni', $datos['dni'], $id)) {
                $errores['dni'] = 'Ya existe una persona registrada con ese DNI.';
            }
        }
        if ($datos['cargo'] !== null && mb_strlen($datos['cargo']) > 150) {
            $errores['cargo'] = 'El cargo admite como máximo 150 caracteres.';
        }
        if ($datos['email'] !== null && (filter_var($datos['email'], FILTER_VALIDATE_EMAIL) === false || mb_strlen($datos['email']) > 150)) {
            $errores['email'] = 'Ingrese un correo electrónico válido.';
        }
        if ($datos['telefono'] !== null && preg_match('/^[0-9 +()#\-]{3,30}$/', $datos['telefono']) !== 1) {
            $errores['telefono'] = 'Ingrese un teléfono o anexo válido.';
        }

        $oficina = $datos['oficina_id'] > 0 ? $this->oficinas->find($datos['oficina_id']) : null;
        if ($oficina === null) {
            $errores['oficina_id'] = 'Seleccione una oficina.';
        } elseif ((int) $oficina['activo'] !== 1 && $datos['oficina_id'] !== $oficinaActual) {
            $errores['oficina_id'] = 'La oficina seleccionada está inactiva.';
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return $datos;
    }

    private function traducirError(DatabaseException $e): Throwable
    {
        if ($e->esDuplicado() && $e->getConstraint() === 'uq_personal_dni') {
            return ValidationException::campo('dni', 'Ya existe una persona registrada con ese DNI.');
        }

        return $e;
    }
}
