<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

/**
 * Usuarios del sistema (ADMINISTRADOR / TECNICO).
 */
final class UsuarioSistemaModel extends Model
{
    protected string $table = 'usuarios_sistema';

    protected array $fillable = [
        'usuario',
        'password_hash',
        'nombres',
        'apellidos',
        'dni',
        'cargo',
        'email',
        'rol',
        'puede_firmar',
        'activo',
        'intentos_fallidos',
        'bloqueado_hasta',
        'debe_cambiar_password',
        'ultimo_acceso',
    ];

    /** @return array<string, mixed>|null */
    public function findByUsuario(string $usuario): ?array
    {
        $fila = $this->db->run(
            'SELECT * FROM usuarios_sistema WHERE usuario = :usuario LIMIT 1',
            ['usuario' => $usuario]
        )->fetch();

        return $fila === false ? null : $fila;
    }

    /**
     * Listado paginado con búsqueda por usuario, nombres, apellidos, DNI o email.
     *
     * @return array{data: list<array<string, mixed>>, total: int, page: int, per_page: int, last_page: int}
     */
    public function buscar(string $texto, ?string $rol, ?int $activo, int $page, int $perPage = 15): array
    {
        $condiciones = [];
        $params = [];

        if ($texto !== '') {
            $condiciones[] = '(usuario LIKE :q1 OR nombres LIKE :q2 OR apellidos LIKE :q3 OR dni LIKE :q4 OR email LIKE :q5)';
            $like = '%' . addcslashes($texto, '%_\\') . '%';
            $params += ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like, 'q5' => $like];
        }
        if ($rol !== null) {
            $condiciones[] = 'rol = :rol';
            $params['rol'] = $rol;
        }
        if ($activo !== null) {
            $condiciones[] = 'activo = :activo';
            $params['activo'] = $activo;
        }

        return $this->paginate(
            $page,
            $perPage,
            implode(' AND ', $condiciones),
            $params,
            'activo DESC, apellidos ASC, nombres ASC',
            'id, usuario, nombres, apellidos, dni, cargo, email, rol, puede_firmar, activo, intentos_fallidos, bloqueado_hasta, ultimo_acceso'
        );
    }

    /**
     * Usuarios activos para elegir técnico en actas e informes.
     *
     * @return list<array{id: int, nombre: string, rol: string}>
     */
    public function activosParaSelect(?int $incluirId = null): array
    {
        return $this->db->run(
            "SELECT id, CONCAT(apellidos, ', ', nombres) AS nombre, rol
               FROM usuarios_sistema
              WHERE activo = 1 OR id = :incluir
              ORDER BY apellidos, nombres",
            ['incluir' => $incluirId ?? 0]
        )->fetchAll();
    }

    public function contarAdministradoresActivos(?int $excluirId = null): int
    {
        $sql = "SELECT COUNT(*) FROM usuarios_sistema WHERE rol = 'ADMINISTRADOR' AND activo = 1";
        $params = [];
        if ($excluirId !== null) {
            $sql .= ' AND id <> :id';
            $params['id'] = $excluirId;
        }

        return (int) $this->db->run($sql, $params)->fetchColumn();
    }

    public function registrarIntentoFallido(int $id, int $maxIntentos, int $minutosBloqueo): int
    {
        $this->db->run(
            'UPDATE usuarios_sistema SET intentos_fallidos = intentos_fallidos + 1 WHERE id = :id',
            ['id' => $id]
        );
        $intentos = (int) $this->db->run(
            'SELECT intentos_fallidos FROM usuarios_sistema WHERE id = :id',
            ['id' => $id]
        )->fetchColumn();

        if ($intentos >= $maxIntentos) {
            $this->db->run(
                'UPDATE usuarios_sistema
                    SET intentos_fallidos = 0,
                        bloqueado_hasta = DATE_ADD(NOW(), INTERVAL :minutos MINUTE)
                  WHERE id = :id',
                ['minutos' => $minutosBloqueo, 'id' => $id]
            );
        }

        return $intentos;
    }

    public function registrarAccesoExitoso(int $id): void
    {
        $this->db->run(
            'UPDATE usuarios_sistema
                SET intentos_fallidos = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
              WHERE id = :id',
            ['id' => $id]
        );
    }

    /** Minutos que faltan para el desbloqueo (0 si no está bloqueado). */
    public function minutosBloqueoRestantes(int $id): int
    {
        $segundos = $this->db->run(
            'SELECT GREATEST(TIMESTAMPDIFF(SECOND, NOW(), bloqueado_hasta), 0)
               FROM usuarios_sistema WHERE id = :id AND bloqueado_hasta IS NOT NULL',
            ['id' => $id]
        )->fetchColumn();

        return $segundos === false || $segundos === null ? 0 : (int) ceil(((int) $segundos) / 60);
    }
}
