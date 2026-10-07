<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Config\DatabaseException;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\CatalogoSoftwareModel;
use App\Models\ChecklistItemModel;
use App\Models\ParametroModel;
use DateTimeImmutable;
use Throwable;

/**
 * Parámetros del sistema y catálogos administrables (software y checklist).
 * Solo ADMINISTRADOR. Todo cambio queda en la auditoría.
 */
final class ConfiguracionService
{
    /** Rangos específicos de parámetros conocidos (el resto se valida por su tipo). */
    private const RANGOS = [
        'periodicidad_mant_meses' => [1, 60],
        'vida_util_meses_default' => [1, 600],
        'actas_enlace_dias_vigencia' => [1, 365],
    ];
    public const TIPOS_EQUIPO = ['PC', 'LAPTOP', 'IMPRESORA'];

    private ParametroModel $parametros;
    private CatalogoSoftwareModel $software;
    private ChecklistItemModel $checklist;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->parametros = new ParametroModel();
        $this->software = new CatalogoSoftwareModel();
        $this->checklist = new ChecklistItemModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /**
     * Guarda los parámetros editables enviados. Devuelve cuántos cambiaron.
     *
     * @param array<string, mixed> $valores clave => valor
     */
    public function guardarParametros(array $valores, int $usuarioId): int
    {
        $actuales = [];
        foreach ($this->parametros->todos() as $p) {
            $actuales[(string) $p['clave']] = $p;
        }

        $errores = [];
        $cambios = [];
        foreach ($valores as $clave => $valor) {
            $clave = (string) $clave;
            $p = $actuales[$clave] ?? null;
            if ($p === null || (int) $p['editable'] !== 1 || !is_scalar($valor)) {
                continue;
            }
            $valor = trim((string) $valor);
            $error = $this->validarParametro($clave, (string) $p['tipo'], $valor);
            if ($error !== null) {
                $errores['param_' . $clave] = $error;
                continue;
            }
            if ($valor !== (string) $p['valor']) {
                $cambios[$clave] = ['antes' => (string) $p['valor'], 'despues' => $valor];
            }
        }
        if ($errores !== []) {
            throw new ValidationException($errores);
        }

        $this->db->beginTransaction();
        try {
            foreach ($cambios as $clave => $c) {
                $this->parametros->actualizarValor($clave, $c['despues'], $usuarioId);
                $this->auditoria->registrar(AuditoriaService::EDITAR, 'parametros', $clave, ['valor' => $c['antes']], ['valor' => $c['despues']], $usuarioId);
            }
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return count($cambios);
    }

    /** @param array<string, mixed> $e */
    public function guardarSoftware(?int $id, array $e, int $usuarioId): int
    {
        $nombre = trim((string) preg_replace('/\s+/u', ' ', is_scalar($e['nombre'] ?? null) ? (string) $e['nombre'] : ''));
        $descripcion = is_scalar($e['descripcion'] ?? null) ? trim((string) $e['descripcion']) : '';
        $orden = $this->orden($e['orden'] ?? null);

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores['sw_nombre'] = 'Ingrese el nombre del software (máximo 100 caracteres).';
        } elseif ($this->software->existeValor('nombre', $nombre, $id)) {
            $errores['sw_nombre'] = 'Ya existe un software con ese nombre en el catálogo.';
        }
        if (mb_strlen($descripcion) > 255) {
            $errores['sw_descripcion'] = 'La descripción admite como máximo 255 caracteres.';
        }
        if ($orden === null) {
            $errores['sw_orden'] = 'El orden debe ser un número entre 0 y 9999.';
        }
        if ($errores !== []) {
            throw new ValidationException($errores, reset($errores));
        }

        $datos = ['nombre' => $nombre, 'descripcion' => $descripcion === '' ? null : $descripcion, 'orden' => $orden];

        return $this->guardarCatalogo($this->software, 'catalogo_software', $id, $datos, $usuarioId);
    }

    /** @param array<string, mixed> $e */
    public function guardarChecklist(?int $id, array $e, int $usuarioId): int
    {
        $categoria = is_scalar($e['categoria'] ?? null) ? (string) $e['categoria'] : '';
        $descripcion = trim((string) preg_replace('/\s+/u', ' ', is_scalar($e['descripcion'] ?? null) ? (string) $e['descripcion'] : ''));
        $aplica = is_array($e['aplica_a'] ?? null) ? array_values(array_intersect(self::TIPOS_EQUIPO, $e['aplica_a'])) : [];
        $orden = $this->orden($e['orden'] ?? null);

        $errores = [];
        if (!in_array($categoria, ChecklistItemModel::CATEGORIAS, true)) {
            $errores['ck_categoria'] = 'Seleccione la categoría (Físico, Lógico o Red).';
        }
        if ($descripcion === '' || mb_strlen($descripcion) > 150) {
            $errores['ck_descripcion'] = 'Ingrese la descripción de la actividad (máximo 150 caracteres).';
        } elseif (!isset($errores['ck_categoria']) && $this->checklist->existeDescripcion($categoria, $descripcion, $id)) {
            $errores['ck_descripcion'] = 'Ya existe esa actividad en la misma categoría.';
        }
        if ($aplica === []) {
            $errores['ck_aplica_a'] = 'Marque al menos un tipo de equipo al que aplica.';
        }
        if ($orden === null) {
            $errores['ck_orden'] = 'El orden debe ser un número entre 0 y 9999.';
        }
        if ($errores !== []) {
            throw new ValidationException($errores, reset($errores));
        }

        $datos = ['categoria' => $categoria, 'descripcion' => $descripcion, 'aplica_a' => implode(',', $aplica), 'orden' => $orden];

        return $this->guardarCatalogo($this->checklist, 'checklist_items', $id, $datos, $usuarioId);
    }

    /** Activa o desactiva un ítem de catálogo ('software' | 'checklist'). Devuelve el nuevo estado. */
    public function alternarActivo(string $catalogo, int $id, int $usuarioId): bool
    {
        [$modelo, $tabla] = match ($catalogo) {
            'software'  => [$this->software, 'catalogo_software'],
            'checklist' => [$this->checklist, 'checklist_items'],
            default     => throw new HttpException(404),
        };

        $this->db->beginTransaction();
        try {
            $fila = $modelo->findForUpdate($id);
            if ($fila === null) {
                throw new HttpException(404, 'El elemento no existe.');
            }
            $nuevo = (int) $fila['activo'] === 1 ? 0 : 1;
            $modelo->update($id, ['activo' => $nuevo]);
            $this->auditoria->registrar(
                $nuevo === 0 ? AuditoriaService::ELIMINAR : AuditoriaService::EDITAR,
                $tabla,
                $id,
                ['activo' => (int) $fila['activo']],
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
     * @param CatalogoSoftwareModel|ChecklistItemModel $modelo
     * @param array<string, mixed> $datos
     */
    private function guardarCatalogo(object $modelo, string $tabla, ?int $id, array $datos, int $usuarioId): int
    {
        $this->db->beginTransaction();
        try {
            if ($id === null) {
                $id = $modelo->insert($datos + ['activo' => 1]);
                $this->auditoria->registrar(AuditoriaService::CREAR, $tabla, $id, null, $modelo->find($id), $usuarioId);
            } else {
                $antes = $modelo->findForUpdate($id);
                if ($antes === null) {
                    throw new HttpException(404, 'El elemento no existe.');
                }
                $modelo->update($id, $datos);
                $this->auditoria->registrar(AuditoriaService::EDITAR, $tabla, $id, $antes, $modelo->find($id), $usuarioId);
            }
            $this->db->commit();

            return $id;
        } catch (DatabaseException $e) {
            $this->db->rollBack();
            throw $e->esDuplicado() ? ValidationException::campo('catalogo', 'Ya existe un elemento con ese nombre.') : $e;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function validarParametro(string $clave, string $tipo, string $valor): ?string
    {
        // Correo de la Jefa de la OTIC: opcional (vacío = las actas se envían sin copia).
        if ($clave === 'jefe_otic_email') {
            if ($valor === '') {
                return null;
            }

            return filter_var($valor, FILTER_VALIDATE_EMAIL) !== false && mb_strlen($valor) <= 150
                ? null
                : 'Ingrese un correo electrónico válido (o déjelo vacío para no enviar copia).';
        }
        if ($valor === '') {
            return 'Este parámetro no puede quedar vacío.';
        }

        switch ($tipo) {
            case 'ENTERO':
                if (preg_match('/^-?\d{1,9}$/', $valor) !== 1) {
                    return 'Debe ser un número entero.';
                }
                [$min, $max] = self::RANGOS[$clave] ?? [0, 999999999];
                if ((int) $valor < $min || (int) $valor > $max) {
                    return sprintf('Debe estar entre %d y %d.', $min, $max);
                }

                return null;
            case 'DECIMAL':
                return preg_match('/^-?\d{1,12}(\.\d{1,4})?$/', $valor) === 1 ? null : 'Debe ser un número (use punto decimal).';
            case 'BOOLEANO':
                return in_array($valor, ['0', '1'], true) ? null : 'Debe ser 0 o 1.';
            case 'FECHA':
                $f = DateTimeImmutable::createFromFormat('!Y-m-d', $valor);

                return $f !== false && $f->format('Y-m-d') === $valor ? null : 'Debe ser una fecha válida (AAAA-MM-DD).';
            default:
                return mb_strlen($valor) > 500 ? 'Máximo 500 caracteres.' : null;
        }
    }

    private function orden(mixed $valor): ?int
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';
        if ($texto === '') {
            return 0;
        }

        return preg_match('/^\d{1,4}$/', $texto) === 1 ? (int) $texto : null;
    }
}
