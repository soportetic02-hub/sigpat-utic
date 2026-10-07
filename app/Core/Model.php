<?php

declare(strict_types=1);

namespace App\Core;

use App\Config\Database;
use InvalidArgumentException;

/**
 * Modelo base: acceso a datos con sentencias preparadas.
 *
 * Cada modelo define $table, $primaryKey y $fillable (lista blanca de columnas
 * que pueden insertarse o actualizarse). Los nombres de columnas nunca provienen
 * del usuario.
 */
abstract class Model
{
    protected string $table = '';
    protected string $primaryKey = 'id';

    /** @var list<string> */
    protected array $fillable = [];

    protected Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Igual que find() pero bloquea la fila hasta el fin de la transacción.
     *
     * @return array<string, mixed>|null
     */
    public function findForUpdate(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id LIMIT 1 FOR UPDATE",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return list<array<string, mixed>> */
    public function all(string $orderBy = 'id ASC'): array
    {
        $orderBy = $this->validarOrden($orderBy);

        return $this->db->run("SELECT * FROM {$this->table} ORDER BY {$orderBy}")->fetchAll();
    }

    /**
     * Paginación genérica. $where es un fragmento SQL escrito por el desarrollador
     * con marcadores (:nombre); los valores van siempre en $params.
     *
     * @param array<string, mixed> $params
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function paginate(
        int $page = 1,
        int $perPage = 15,
        string $where = '',
        array $params = [],
        string $orderBy = 'id DESC',
        string $select = '*',
        string $from = ''
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $orderBy = $this->validarOrden($orderBy);
        $from = $from !== '' ? $from : $this->table;
        $whereSql = $where !== '' ? 'WHERE ' . $where : '';

        $total = (int) $this->db->run("SELECT COUNT(*) FROM {$from} {$whereSql}", $params)->fetchColumn();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);
        $offset = ($page - 1) * $perPage;

        $data = $this->db->run(
            "SELECT {$select} FROM {$from} {$whereSql} ORDER BY {$orderBy} LIMIT :__limit OFFSET :__offset",
            array_merge($params, ['__limit' => $perPage, '__offset' => $offset])
        )->fetchAll();

        return [
            'data'      => $data,
            'total'     => $total,
            'page'      => $page,
            'per_page'  => $perPage,
            'last_page' => $lastPage,
        ];
    }

    /** @param array<string, mixed> $datos */
    public function insert(array $datos): int
    {
        $datos = $this->filtrarFillable($datos);
        $columnas = array_keys($datos);
        $marcadores = array_map(static fn (string $c): string => ':' . $c, $columnas);

        $this->db->run(
            sprintf('INSERT INTO %s (%s) VALUES (%s)', $this->table, implode(', ', $columnas), implode(', ', $marcadores)),
            $datos
        );

        return $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $datos */
    public function update(int $id, array $datos): void
    {
        $datos = $this->filtrarFillable($datos);
        $sets = array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($datos));

        $this->db->run(
            sprintf('UPDATE %s SET %s WHERE %s = :__pk', $this->table, implode(', ', $sets), $this->primaryKey),
            array_merge($datos, ['__pk' => $id])
        );
    }

    /**
     * ¿Existe otro registro con este valor en la columna? (para validar unicidad).
     */
    public function existeValor(string $columna, mixed $valor, ?int $excluirId = null): bool
    {
        if (!in_array($columna, $this->fillable, true)) {
            throw new InvalidArgumentException('Columna no permitida: ' . $columna);
        }
        if ($valor === null || $valor === '') {
            return false;
        }

        $sql = "SELECT 1 FROM {$this->table} WHERE {$columna} = :valor";
        $params = ['valor' => $valor];
        if ($excluirId !== null) {
            $sql .= " AND {$this->primaryKey} <> :excluir";
            $params['excluir'] = $excluirId;
        }

        return $this->db->run($sql . ' LIMIT 1', $params)->fetchColumn() !== false;
    }

    /**
     * @param array<string, mixed> $datos
     * @return array<string, mixed>
     */
    protected function filtrarFillable(array $datos): array
    {
        $filtrados = array_intersect_key($datos, array_flip($this->fillable));
        if ($filtrados === []) {
            throw new InvalidArgumentException('No hay columnas válidas para guardar en ' . $this->table);
        }

        return $filtrados;
    }

    private function validarOrden(string $orderBy): string
    {
        if (preg_match('/^[a-z0-9_.]+( (ASC|DESC))?(, ?[a-z0-9_.]+( (ASC|DESC))?)*$/i', $orderBy) !== 1) {
            throw new InvalidArgumentException('Orden no válido: ' . $orderBy);
        }

        return $orderBy;
    }
}
