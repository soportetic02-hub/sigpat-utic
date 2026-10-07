<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Parámetros del sistema (clave/valor).
 */
final class ParametroModel extends Model
{
    protected string $table = 'parametros';
    protected string $primaryKey = 'clave';

    protected array $fillable = [
        'clave',
        'valor',
        'tipo',
        'descripcion',
        'editable',
        'updated_by',
    ];

    /** @var array<string, string>|null */
    private static ?array $cache = null;

    public function obtener(string $clave, ?string $defecto = null): ?string
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach ($this->db->run('SELECT clave, valor FROM parametros')->fetchAll() as $fila) {
                self::$cache[(string) $fila['clave']] = (string) $fila['valor'];
            }
        }

        return self::$cache[$clave] ?? $defecto;
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return $this->db->run(
            "SELECT p.clave, p.valor, p.tipo, p.descripcion, p.editable, p.updated_at,
                    CONCAT(u.nombres, ' ', u.apellidos) AS actualizado_por
               FROM parametros p LEFT JOIN usuarios_sistema u ON u.id = p.updated_by
              ORDER BY p.clave"
        )->fetchAll();
    }

    public function actualizarValor(string $clave, string $valor, int $usuarioId): void
    {
        $this->db->run(
            'UPDATE parametros SET valor = :valor, updated_by = :usuario WHERE clave = :clave AND editable = 1',
            ['valor' => $valor, 'usuario' => $usuarioId, 'clave' => $clave]
        );
        self::$cache = null;
    }

    public function obtenerEntero(string $clave, int $defecto): int
    {
        $valor = $this->obtener($clave);

        return $valor !== null && preg_match('/^-?\d+$/', trim($valor)) === 1 ? (int) $valor : $defecto;
    }
}
