<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\OficinaModel;
use Throwable;

/**
 * Oficinas jerárquicas: alta, edición, árbol y borrado lógico.
 */
final class OficinaService
{
    public const TIPOS = ['DIRECCION', 'DEPARTAMENTO', 'OFICINA'];

    private OficinaModel $oficinas;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->oficinas = new OficinaModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed> */
    public function obtener(int $id): array
    {
        $oficina = $this->oficinas->find($id);
        if ($oficina === null) {
            throw new HttpException(404, 'La oficina no existe.');
        }

        return $oficina;
    }

    /**
     * Árbol de oficinas: cada nodo lleva 'hijos'.
     *
     * @return list<array<string, mixed>>
     */
    public function arbol(bool $incluirInactivas): array
    {
        $filas = $this->oficinas->todasConConteo($incluirInactivas);
        $porPadre = [];
        $ids = array_column($filas, 'id');
        foreach ($filas as $fila) {
            // Una oficina cuyo padre no está en la lista (inactivo y oculto) se muestra como raíz.
            $padre = $fila['padre_id'] !== null && in_array($fila['padre_id'], $ids, true) ? (int) $fila['padre_id'] : 0;
            $porPadre[$padre][] = $fila;
        }

        $construir = static function (int $padre) use (&$construir, $porPadre): array {
            $nodos = [];
            foreach ($porPadre[$padre] ?? [] as $fila) {
                $fila['hijos'] = $construir((int) $fila['id']);
                $nodos[] = $fila;
            }

            return $nodos;
        };

        return $construir(0);
    }

    /**
     * Opciones para un <select>, en orden jerárquico con sangría.
     * Solo oficinas activas, más $incluirId aunque esté inactiva (valor actual de un registro).
     *
     * @return list<array{id: int, etiqueta: string, nivel: int}>
     */
    public function opcionesSelect(?int $incluirId = null, ?int $excluirRamaDe = null): array
    {
        $filas = $this->oficinas->listaBasica();
        $porPadre = [];
        foreach ($filas as $fila) {
            $porPadre[$fila['padre_id'] === null ? 0 : (int) $fila['padre_id']][] = $fila;
        }

        $opciones = [];
        $recorrer = static function (int $padre, int $nivel) use (&$recorrer, &$opciones, $porPadre, $incluirId, $excluirRamaDe): void {
            foreach ($porPadre[$padre] ?? [] as $fila) {
                $id = (int) $fila['id'];
                if ($excluirRamaDe !== null && $id === $excluirRamaDe) {
                    continue;
                }
                if ((int) $fila['activo'] === 1 || $id === $incluirId) {
                    $etiqueta = $fila['nombre'] . ($fila['siglas'] !== null ? ' (' . $fila['siglas'] . ')' : '');
                    if ((int) $fila['activo'] !== 1) {
                        $etiqueta .= ' [inactiva]';
                    }
                    $opciones[] = ['id' => $id, 'etiqueta' => $etiqueta, 'nivel' => $nivel];
                }
                $recorrer($id, $nivel + 1);
            }
        };
        $recorrer(0, 0);

        return $opciones;
    }

    /** @param array<string, mixed> $entrada */
    public function crear(array $entrada, int $usuarioId): int
    {
        $datos = $this->validar($entrada, null);
        $datos['activo'] = 1;

        $this->db->beginTransaction();
        try {
            $id = $this->oficinas->insert($datos);
            $this->auditoria->registrar(AuditoriaService::CREAR, 'oficinas', $id, null, $this->oficinas->find($id), $usuarioId);
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $e->esDuplicado() ? ValidationException::campo('nombre', 'Ya existe una oficina con ese nombre en la misma dependencia.') : $e;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @param array<string, mixed> $entrada */
    public function actualizar(int $id, array $entrada, int $usuarioId): void
    {
        $antes = $this->obtener($id);
        $datos = $this->validar($entrada, $id);

        $this->db->beginTransaction();
        try {
            $this->oficinas->update($id, $datos);
            $this->auditoria->registrar(AuditoriaService::EDITAR, 'oficinas', $id, $antes, $this->oficinas->find($id), $usuarioId);
            $this->db->commit();
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $e->esDuplicado() ? ValidationException::campo('nombre', 'Ya existe una oficina con ese nombre en la misma dependencia.') : $e;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Activa o desactiva (borrado lógico). No se desactiva una oficina con
     * personal activo, equipos no dados de baja u oficinas hijas activas.
     */
    public function alternarActivo(int $id, int $usuarioId): bool
    {
        $this->db->beginTransaction();
        try {
            $oficina = $this->oficinas->findForUpdate($id);
            if ($oficina === null) {
                throw new HttpException(404, 'La oficina no existe.');
            }
            $nuevo = (int) $oficina['activo'] === 1 ? 0 : 1;

            if ($nuevo === 0) {
                $dep = $this->oficinas->dependenciasActivas($id);
                $motivos = [];
                if ($dep['personal'] > 0) {
                    $motivos[] = $dep['personal'] . ' persona(s) activa(s)';
                }
                if ($dep['equipos'] > 0) {
                    $motivos[] = $dep['equipos'] . ' equipo(s) no dado(s) de baja';
                }
                if ($dep['hijas'] > 0) {
                    $motivos[] = $dep['hijas'] . ' oficina(s) dependiente(s) activa(s)';
                }
                if ($motivos !== []) {
                    throw ValidationException::campo('activo', 'No se puede desactivar la oficina porque tiene ' . implode(', ', $motivos) . '. Reubíquelos primero.');
                }
            } elseif ($oficina['padre_id'] !== null) {
                $padre = $this->oficinas->find((int) $oficina['padre_id']);
                if ($padre !== null && (int) $padre['activo'] !== 1) {
                    throw ValidationException::campo('activo', 'Active primero la oficina superior "' . $padre['nombre'] . '".');
                }
            }

            $this->oficinas->update($id, ['activo' => $nuevo]);
            $this->auditoria->registrar(
                $nuevo === 0 ? AuditoriaService::ELIMINAR : AuditoriaService::EDITAR,
                'oficinas',
                $id,
                ['nombre' => $oficina['nombre'], 'activo' => (int) $oficina['activo']],
                ['nombre' => $oficina['nombre'], 'activo' => $nuevo],
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
    private function validar(array $entrada, ?int $id): array
    {
        $texto = static fn (string $c): string => trim((string) ($entrada[$c] ?? ''));
        $padreTexto = $texto('padre_id');

        $datos = [
            'nombre'    => preg_replace('/\s+/u', ' ', $texto('nombre')) ?? '',
            'siglas'    => $texto('siglas') !== '' ? mb_strtoupper($texto('siglas')) : null,
            'tipo'      => $texto('tipo'),
            'padre_id'  => ctype_digit($padreTexto) ? (int) $padreTexto : null,
            'ubicacion' => $texto('ubicacion') !== '' ? $texto('ubicacion') : null,
            'telefono'  => $texto('telefono') !== '' ? $texto('telefono') : null,
        ];

        $errores = [];
        if ($datos['nombre'] === '' || mb_strlen($datos['nombre']) > 150) {
            $errores['nombre'] = 'Ingrese el nombre de la oficina (máximo 150 caracteres).';
        }
        if ($datos['siglas'] !== null && mb_strlen($datos['siglas']) > 20) {
            $errores['siglas'] = 'Las siglas admiten como máximo 20 caracteres.';
        }
        if (!in_array($datos['tipo'], self::TIPOS, true)) {
            $errores['tipo'] = 'Seleccione un tipo válido.';
        }
        if ($datos['ubicacion'] !== null && mb_strlen($datos['ubicacion']) > 150) {
            $errores['ubicacion'] = 'La ubicación admite como máximo 150 caracteres.';
        }
        if ($datos['telefono'] !== null && preg_match('/^[0-9 +()#\-]{3,30}$/', $datos['telefono']) !== 1) {
            $errores['telefono'] = 'Ingrese un teléfono o anexo válido.';
        }

        if ($padreTexto !== '' && $datos['padre_id'] === null) {
            $errores['padre_id'] = 'Seleccione una oficina superior válida.';
        } elseif ($datos['padre_id'] !== null) {
            $padre = $this->oficinas->find($datos['padre_id']);
            if ($padre === null) {
                $errores['padre_id'] = 'La oficina superior no existe.';
            } elseif ($id !== null && $this->esDescendienteOIgual($datos['padre_id'], $id)) {
                $errores['padre_id'] = 'Una oficina no puede depender de sí misma ni de una de sus dependencias.';
            } elseif ((int) $padre['activo'] !== 1) {
                $actual = $id !== null ? $this->oficinas->find($id) : null;
                if ($actual === null || (int) ($actual['padre_id'] ?? 0) !== $datos['padre_id']) {
                    $errores['padre_id'] = 'La oficina superior está inactiva.';
                }
            }
        }

        if (!isset($errores['nombre']) && !isset($errores['padre_id'])
            && $this->oficinas->existeNombreEnPadre($datos['nombre'], $datos['padre_id'], $id)) {
            $errores['nombre'] = 'Ya existe una oficina con ese nombre en la misma dependencia.';
        }

        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        return $datos;
    }

    /** ¿$candidatoId es $id o uno de sus descendientes? */
    private function esDescendienteOIgual(int $candidatoId, int $id): bool
    {
        $padres = [];
        foreach ($this->oficinas->listaBasica() as $fila) {
            $padres[(int) $fila['id']] = $fila['padre_id'] === null ? null : (int) $fila['padre_id'];
        }

        $actual = $candidatoId;
        $visitados = [];
        while ($actual !== null && !isset($visitados[$actual])) {
            if ($actual === $id) {
                return true;
            }
            $visitados[$actual] = true;
            $actual = $padres[$actual] ?? null;
        }

        return false;
    }
}
