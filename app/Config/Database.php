<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Logger;
use LogicException;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Conexión PDO única (singleton) a MariaDB.
 *
 * Las transacciones admiten anidamiento lógico: solo la llamada más externa
 * inicia y confirma la transacción real, de modo que un servicio puede llamar a
 * otro (p. ej. CorrelativoService dentro de MantenimientoService) sin romperla.
 */
final class Database
{
    private static ?Database $instancia = null;

    private PDO $pdo;
    private int $nivelTransaccion = 0;

    private function __construct()
    {
        $cfg = config('db');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            $this->pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
            if (preg_match('/^[+-](0\d|1[0-4]):[0-5]\d$/', (string) $cfg['tz']) !== 1) {
                throw new PDOException('Desfase horario no válido en la configuración.');
            }
            $this->pdo->prepare('SET time_zone = ?')->execute([$cfg['tz']]);
        } catch (PDOException $e) {
            Logger::error('Fallo de conexión a la base de datos', [
                'host'    => $cfg['host'],
                'db'      => $cfg['name'],
                'codigo'  => $e->getCode(),
                'mensaje' => $e->getMessage(),
            ]);
            throw new DatabaseException('No se pudo conectar con la base de datos.', $e);
        }
    }

    public static function getInstance(): self
    {
        if (self::$instancia === null) {
            self::$instancia = new self();
        }

        return self::$instancia;
    }

    public static function pdo(): PDO
    {
        return self::getInstance()->pdo;
    }

    private function __clone()
    {
    }

    public function __wakeup(): void
    {
        throw new LogicException('No se puede deserializar la conexión a la base de datos.');
    }

    /**
     * Prepara y ejecuta una sentencia con parámetros. Toda excepción de PDO se
     * registra en app.log y se relanza como DatabaseException sin detalles.
     *
     * @param array<string|int, mixed> $params
     */
    public function run(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            foreach ($params as $clave => $valor) {
                $nombre = is_int($clave) ? $clave + 1 : (str_starts_with($clave, ':') ? $clave : ':' . $clave);
                $tipo = match (true) {
                    is_int($valor)  => PDO::PARAM_INT,
                    is_bool($valor) => PDO::PARAM_BOOL,
                    $valor === null => PDO::PARAM_NULL,
                    default         => PDO::PARAM_STR,
                };
                $stmt->bindValue($nombre, $valor, $tipo);
            }
            $stmt->execute();

            return $stmt;
        } catch (PDOException $e) {
            $excepcion = new DatabaseException('Ocurrió un error al acceder a la base de datos.', $e);
            if (!$excepcion->esViolacionIntegridad()) {
                Logger::error('Error SQL', [
                    'sqlstate' => $excepcion->getSqlState(),
                    'codigo'   => $excepcion->getDriverCode(),
                    'mensaje'  => $e->getMessage(),
                    'sql'      => preg_replace('/\s+/', ' ', $sql),
                ]);
            }
            throw $excepcion;
        }
    }

    public function lastInsertId(): int
    {
        return (int) $this->pdo->lastInsertId();
    }

    public function beginTransaction(): void
    {
        if ($this->nivelTransaccion === 0) {
            try {
                $this->pdo->beginTransaction();
            } catch (PDOException $e) {
                Logger::error('No se pudo iniciar la transacción', ['mensaje' => $e->getMessage()]);
                throw new DatabaseException('No se pudo iniciar la transacción.', $e);
            }
        }
        $this->nivelTransaccion++;
    }

    public function commit(): void
    {
        if ($this->nivelTransaccion === 0) {
            throw new LogicException('commit() sin transacción activa.');
        }
        $this->nivelTransaccion--;
        if ($this->nivelTransaccion === 0) {
            try {
                $this->pdo->commit();
            } catch (PDOException $e) {
                Logger::error('No se pudo confirmar la transacción', ['mensaje' => $e->getMessage()]);
                throw new DatabaseException('No se pudo confirmar la transacción.', $e);
            }
        }
    }

    /** Revierte toda la transacción (en cualquier nivel de anidamiento). */
    public function rollBack(): void
    {
        $this->nivelTransaccion = 0;
        if ($this->pdo->inTransaction()) {
            try {
                $this->pdo->rollBack();
            } catch (PDOException $e) {
                Logger::error('No se pudo revertir la transacción', ['mensaje' => $e->getMessage()]);
            }
        }
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }
}
