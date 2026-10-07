<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Registro de auditoría (solo inserción desde AuditoriaService).
 */
final class AuditoriaModel extends Model
{
    protected string $table = 'auditoria';

    protected array $fillable = [
        'usuario_id',
        'usuario_login',
        'accion',
        'tabla',
        'registro_id',
        'datos_antes',
        'datos_despues',
        'ip',
        'user_agent',
    ];

    public const ACCIONES = ['CREAR', 'EDITAR', 'ELIMINAR', 'LOGIN', 'LOGIN_FALLIDO', 'LOGOUT', 'ASIGNAR', 'ASIGNAR_SLOT', 'LIBERAR_SLOT', 'CERRAR', 'EMITIR', 'BAJA', 'VER_CONTRASENA', 'FIRMAR', 'ENVIAR', 'CONFIRMAR_RECEPCION'];

    /**
     * @param array{usuario_id?: ?int, accion?: ?string, tabla?: ?string, desde?: ?string, hasta?: ?string, registro_id?: string} $f
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function listado(array $f, int $page, int $perPage = 30): array
    {
        $condiciones = [];
        $params = [];
        if (!empty($f['usuario_id'])) {
            $condiciones[] = 'a.usuario_id = :usuario';
            $params['usuario'] = (int) $f['usuario_id'];
        }
        if (!empty($f['accion'])) {
            $condiciones[] = 'a.accion = :accion';
            $params['accion'] = $f['accion'];
        }
        if (!empty($f['tabla'])) {
            $condiciones[] = 'a.tabla = :tabla';
            $params['tabla'] = $f['tabla'];
        }
        if (!empty($f['registro_id'])) {
            $condiciones[] = 'a.registro_id = :registro';
            $params['registro'] = $f['registro_id'];
        }
        if (!empty($f['desde'])) {
            $condiciones[] = 'a.created_at >= :desde';
            $params['desde'] = $f['desde'] . ' 00:00:00';
        }
        if (!empty($f['hasta'])) {
            $condiciones[] = 'a.created_at <= :hasta';
            $params['hasta'] = $f['hasta'] . ' 23:59:59';
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'a.id DESC',
            "a.id, a.usuario_id, a.usuario_login, a.accion, a.tabla, a.registro_id, a.ip, a.created_at,
             a.datos_antes IS NOT NULL AS tiene_antes, a.datos_despues IS NOT NULL AS tiene_despues,
             CASE WHEN u.id IS NULL THEN NULL ELSE CONCAT(u.nombres, ' ', u.apellidos) END AS usuario_nombre",
            'auditoria a LEFT JOIN usuarios_sistema u ON u.id = a.usuario_id'
        );
    }

    /** @return array<string, mixed>|null */
    public function detalle(int $id): ?array
    {
        $fila = $this->db->run(
            "SELECT a.*, CASE WHEN u.id IS NULL THEN NULL ELSE CONCAT(u.nombres, ' ', u.apellidos) END AS usuario_nombre
               FROM auditoria a LEFT JOIN usuarios_sistema u ON u.id = a.usuario_id
              WHERE a.id = :id",
            ['id' => $id]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /** @return list<string> */
    public function tablas(): array
    {
        return array_map(
            static fn (array $f): string => (string) $f['tabla'],
            $this->db->run('SELECT DISTINCT tabla FROM auditoria WHERE tabla IS NOT NULL ORDER BY tabla')->fetchAll()
        );
    }

    /** @return list<array{id: int, nombre: string}> */
    public function usuarios(): array
    {
        return $this->db->run(
            "SELECT DISTINCT u.id, CONCAT(u.apellidos, ', ', u.nombres) AS nombre
               FROM auditoria a INNER JOIN usuarios_sistema u ON u.id = a.usuario_id
              ORDER BY nombre"
        )->fetchAll();
    }
}
