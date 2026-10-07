<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Equipos del inventario patrimonial.
 */
final class EquipoModel extends Model
{
    protected string $table = 'equipos';

    protected array $fillable = [
        'tipo',
        'marca',
        'modelo',
        'nro_serie',
        'codigo_patrimonial',
        'codigo_interno',
        'procesador',
        'ram_gb',
        'disco_tipo',
        'disco_capacidad_gb',
        'sistema_operativo',
        'hostname',
        'mac_lan',
        'ip_lan',
        'mac_wifi',
        'oficina_id',
        'personal_id',
        'estado_operativo',
        'condicion_fisica',
        'recomendado_baja',
        'fecha_baja',
        'fecha_adquisicion',
        'vida_util_meses',
        'periodicidad_mant_meses',
        'valor_adquisicion',
        'orden_compra',
        'proveedor',
        'garantia_hasta',
        'observaciones',
        'created_by',
        'updated_by',
    ];

    /**
     * Equipos cuyo responsable es la persona indicada.
     *
     * @return list<array<string, mixed>>
     */
    public function porPersonal(int $personalId, bool $bloquear = false): array
    {
        return $this->db->run(
            "SELECT e.id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.hostname, e.ip_lan,
                    e.estado_operativo, e.condicion_fisica, e.oficina_id, e.personal_id,
                    o.nombre AS oficina_nombre, o.siglas AS oficina_siglas
               FROM equipos e
               INNER JOIN oficinas o ON o.id = e.oficina_id
              WHERE e.personal_id = :personal
              ORDER BY e.tipo, e.marca, e.modelo" . ($bloquear ? ' FOR UPDATE' : ''),
            ['personal' => $personalId]
        )->fetchAll();
    }

    /**
     * Equipos que pueden asignarse a un responsable (no dados de baja),
     * buscando por serie, código patrimonial, hostname, IP, marca o modelo.
     *
     * @return list<array<string, mixed>>
     */
    public function buscarAsignables(string $texto, ?int $excluirPersonalId, int $limite = 15): array
    {
        $like = '%' . addcslashes($texto, '%_\\') . '%';
        $sql = "SELECT e.id, e.tipo, e.marca, e.modelo, e.nro_serie, e.codigo_patrimonial, e.hostname, e.ip_lan,
                       e.estado_operativo, e.oficina_id, e.personal_id,
                       o.nombre AS oficina_nombre,
                       CASE WHEN p.id IS NULL THEN NULL ELSE CONCAT(p.nombres, ' ', p.apellidos) END AS personal_nombre
                  FROM equipos e
                  INNER JOIN oficinas o ON o.id = e.oficina_id
                  LEFT JOIN personal p ON p.id = e.personal_id
                 WHERE e.estado_operativo <> 'DE_BAJA'
                   AND (e.nro_serie LIKE :q1 OR e.codigo_patrimonial LIKE :q2 OR e.codigo_interno LIKE :q3
                        OR e.hostname LIKE :q4 OR e.ip_lan LIKE :q5 OR CONCAT(e.marca, ' ', e.modelo) LIKE :q6)";
        $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like, 'q6' => $like];
        if ($excluirPersonalId !== null) {
            $sql .= ' AND (e.personal_id IS NULL OR e.personal_id <> :excluir)';
            $params['excluir'] = $excluirPersonalId;
        }
        $sql .= ' ORDER BY e.personal_id IS NOT NULL, e.tipo, e.marca LIMIT :limite';
        $params['limite'] = $limite;

        return $this->db->run($sql, $params)->fetchAll();
    }

    public const TIPOS = ['PC', 'LAPTOP', 'IMPRESORA'];
    public const ESTADOS = ['OPERATIVO', 'EN_MANTENIMIENTO', 'INOPERATIVO', 'DE_BAJA'];
    public const CONDICIONES = ['BUENO', 'REGULAR', 'MALO'];

    /** Columnas de v_equipos_estado que muestra el listado y exporta el CSV. */
    private const COLUMNAS_LISTADO = 'v.id, v.tipo, v.marca, v.modelo, v.nro_serie, v.codigo_patrimonial, v.codigo_interno,
        v.codigo_inventario_anio, v.procesador, v.ram_gb, v.disco_tipo, v.disco_capacidad_gb, v.sistema_operativo,
        v.hostname, v.ip_lan, v.mac_lan, v.oficina_id, v.oficina_nombre, v.oficina_siglas, v.personal_id,
        v.personal_nombre, v.en_uso, v.estado_operativo, v.condicion_fisica, v.recomendado_baja, v.fecha_adquisicion,
        v.anios_antiguedad, v.fecha_sugerida_baja, v.fecha_ultimo_mantenimiento, v.fecha_proximo_mantenimiento,
        v.dias_retraso_mantenimiento, v.licencia_id';

    /**
     * Listado paginado sobre v_equipos_estado.
     *
     * @param array{q?: string, tipo?: ?string, estado?: ?string, condicion?: ?string, oficina_id?: ?int, recomendado?: bool} $filtros
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listado(array $filtros, int $page, int $perPage = 20): array
    {
        [$where, $params] = $this->condicionesListado($filtros);

        return $this->paginate($page, $perPage, $where, $params, 'v.tipo ASC, v.marca ASC, v.modelo ASC, v.id ASC',
            self::COLUMNAS_LISTADO, 'v_equipos_estado v');
    }

    /**
     * Todas las filas que cumplen los filtros (exportación CSV).
     *
     * @param array{q?: string, tipo?: ?string, estado?: ?string, condicion?: ?string, oficina_id?: ?int, recomendado?: bool} $filtros
     * @return list<array<string, mixed>>
     */
    public function exportar(array $filtros): array
    {
        [$where, $params] = $this->condicionesListado($filtros);

        return $this->db->run(
            'SELECT ' . self::COLUMNAS_LISTADO . ' FROM v_equipos_estado v'
            . ($where !== '' ? ' WHERE ' . $where : '')
            . ' ORDER BY v.tipo, v.marca, v.modelo, v.id',
            $params
        )->fetchAll();
    }

    /**
     * Fila completa de v_equipos_estado más los campos propios de equipos.
     *
     * @return array<string, mixed>|null
     */
    public function ficha(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT v.*, e.periodicidad_mant_meses, e.created_by, e.updated_by,
                    CONCAT(uc.nombres, ' ', uc.apellidos) AS creado_por,
                    CONCAT(uu.nombres, ' ', uu.apellidos) AS actualizado_por
               FROM v_equipos_estado v
               INNER JOIN equipos e ON e.id = v.id
               LEFT JOIN usuarios_sistema uc ON uc.id = e.created_by
               LEFT JOIN usuarios_sistema uu ON uu.id = e.updated_by
              WHERE v.id = :id",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Actas de mantenimiento del equipo (más recientes primero).
     *
     * @return list<array<string, mixed>>
     */
    public function actas(int $equipoId): array
    {
        return $this->db->run(
            "SELECT m.id, m.numero, m.tipo, m.estado, m.fecha_ingreso, m.fecha_salida,
                    CONCAT(u.nombres, ' ', u.apellidos) AS tecnico
               FROM mantenimientos m
               INNER JOIN usuarios_sistema u ON u.id = m.tecnico_id
              WHERE m.equipo_id = :equipo
              ORDER BY m.fecha_ingreso DESC, m.id DESC",
            ['equipo' => $equipoId]
        )->fetchAll();
    }

    /**
     * Informes técnicos que evalúan el equipo.
     *
     * @return list<array<string, mixed>>
     */
    public function informes(int $equipoId): array
    {
        return $this->db->run(
            'SELECT it.id, it.numero, it.asunto, it.fecha, it.accion_requerida, it.estado, ie.diagnostico
               FROM informe_equipos ie
               INNER JOIN informes_tecnicos it ON it.id = ie.informe_id
              WHERE ie.equipo_id = :equipo
              ORDER BY it.fecha DESC, it.id DESC',
            ['equipo' => $equipoId]
        )->fetchAll();
    }

    /** ¿Tiene actas, informes o licencias (aunque estén liberadas)? Entonces no puede eliminarse. */
    public function tieneDocumentosRelacionados(int $equipoId): bool
    {
        return (int) $this->db->run(
            'SELECT (EXISTS(SELECT 1 FROM mantenimientos WHERE equipo_id = :e1)
                  OR EXISTS(SELECT 1 FROM informe_equipos WHERE equipo_id = :e2)
                  OR EXISTS(SELECT 1 FROM informe_evidencias WHERE equipo_id = :e3)
                  OR EXISTS(SELECT 1 FROM licencia_equipos WHERE equipo_id = :e4))',
            ['e1' => $equipoId, 'e2' => $equipoId, 'e3' => $equipoId, 'e4' => $equipoId]
        )->fetchColumn() === 1;
    }

    /**
     * Otro equipo que ya tiene ese valor en una columna única (para mensajes claros).
     *
     * @return array{id: int, tipo: string, marca: string, modelo: string}|null
     */
    public function otroConValor(string $columna, string $valor, ?int $excluirId): ?array
    {
        $permitidas = ['nro_serie', 'codigo_patrimonial', 'codigo_interno', 'hostname', 'mac_lan', 'ip_lan', 'mac_wifi'];
        if (!in_array($columna, $permitidas, true)) {
            throw new \InvalidArgumentException('Columna no permitida: ' . $columna);
        }
        $sql = "SELECT id, tipo, marca, modelo FROM equipos WHERE {$columna} = :valor";
        $params = ['valor' => $valor];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :excluir';
            $params['excluir'] = $excluirId;
        }
        $fila = $this->db->run($sql . ' LIMIT 1', $params)->fetch();

        return $fila === false ? null : $fila;
    }

    /** ¿Tiene un acta de mantenimiento en BORRADOR? */
    public function tieneActaAbierta(int $equipoId): bool
    {
        return $this->db->run(
            "SELECT 1 FROM mantenimientos WHERE equipo_id = :equipo AND estado = 'BORRADOR' LIMIT 1",
            ['equipo' => $equipoId]
        )->fetchColumn() !== false;
    }

    /**
     * Elimina físicamente un equipo sin documentos (corrección de un alta errónea).
     * Borra antes su historial de asignaciones; códigos y puntos de red se borran en cascada.
     */
    public function eliminarDefinitivo(int $equipoId): void
    {
        $this->db->run('DELETE FROM historial_asignaciones WHERE equipo_id = :equipo', ['equipo' => $equipoId]);
        $this->db->run('DELETE FROM equipos WHERE id = :id', ['id' => $equipoId]);
    }

    /**
     * @param array{q?: string, tipo?: ?string, estado?: ?string, condicion?: ?string, oficina_id?: ?int, recomendado?: bool} $f
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function condicionesListado(array $f): array
    {
        $condiciones = [];
        $params = [];

        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $condiciones[] = "(v.nro_serie LIKE :q1 OR v.codigo_patrimonial LIKE :q2 OR v.codigo_interno LIKE :q3
                               OR v.hostname LIKE :q4 OR v.ip_lan LIKE :q5 OR v.mac_lan LIKE :q6
                               OR CONCAT(v.marca, ' ', v.modelo) LIKE :q7 OR v.personal_nombre LIKE :q8
                               OR EXISTS (SELECT 1 FROM equipo_codigos c WHERE c.equipo_id = v.id AND c.codigo LIKE :q9))";
            foreach (range(1, 9) as $i) {
                $params['q' . $i] = $like;
            }
        }
        if (!empty($f['tipo'])) {
            $condiciones[] = 'v.tipo = :tipo';
            $params['tipo'] = $f['tipo'];
        }
        $estado = $f['estado'] ?? null;
        if ($estado === 'TODOS') {
            // sin filtro
        } elseif ($estado !== null && $estado !== '') {
            $condiciones[] = 'v.estado_operativo = :estado';
            $params['estado'] = $estado;
        } else {
            $condiciones[] = "v.estado_operativo <> 'DE_BAJA'";
        }
        if (!empty($f['condicion'])) {
            $condiciones[] = 'v.condicion_fisica = :condicion';
            $params['condicion'] = $f['condicion'];
        }
        if (!empty($f['oficina_id'])) {
            $condiciones[] = 'v.oficina_id = :oficina';
            $params['oficina'] = (int) $f['oficina_id'];
        }
        if (!empty($f['recomendado'])) {
            $condiciones[] = 'v.recomendado_baja = 1';
        }

        return [implode(' AND ', $condiciones), $params];
    }

    /** Actualiza solo la ubicación y el responsable (lo usa AsignacionService). */
    public function actualizarAsignacion(int $id, int $oficinaId, ?int $personalId, ?int $usuarioId): void
    {
        $this->db->run(
            'UPDATE equipos SET oficina_id = :oficina, personal_id = :personal, updated_by = :usuario WHERE id = :id',
            ['oficina' => $oficinaId, 'personal' => $personalId, 'usuario' => $usuarioId, 'id' => $id]
        );
    }
}
