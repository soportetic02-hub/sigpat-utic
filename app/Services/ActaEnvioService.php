<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\View;
use App\Models\MantenimientoEnvioModel;
use App\Models\MantenimientoModel;
use App\Models\ParametroModel;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

/**
 * Envío de actas cerradas por correo y confirmación de recepción.
 *
 * Estados del acta (mantenimientos.estado_envio):
 *   NO_ENVIADO → ENVIADO (el servidor de correo aceptó el mensaje)
 *              → RECIBIDO (el usuario pulsó "Confirmo la recepción" en el enlace del correo)
 *   ERROR: el último intento falló y el acta nunca se envió bien.
 * Abrir el enlace solo registra visto_at: los filtros antispam abren los enlaces
 * automáticamente, por eso la recepción exige pulsar el botón (POST).
 */
final class ActaEnvioService
{
    private MantenimientoModel $actas;
    private MantenimientoEnvioModel $envios;
    private ParametroModel $parametros;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->actas = new MantenimientoModel();
        $this->envios = new MantenimientoEnvioModel();
        $this->parametros = new ParametroModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    public function requiereFirma(): bool
    {
        return $this->parametros->obtener('actas_envio_requiere_firma', '1') === '1';
    }

    /** Correo de la Jefa de la OTIC (parámetro jefe_otic_email), o null si no está configurado. */
    public function correoJefa(): ?string
    {
        $correo = mb_strtolower(trim((string) $this->parametros->obtener('jefe_otic_email', '')));

        return filter_var($correo, FILTER_VALIDATE_EMAIL) !== false ? $correo : null;
    }

    /**
     * Envía el acta por correo. Devuelve true si el servidor aceptó el correo;
     * false si falló (el intento queda registrado con su error en $error).
     */
    public function enviar(int $actaId, string $destinatario, int $usuarioId, ?string &$error = null): bool
    {
        $acta = $this->actas->detalle($actaId);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }
        if ($acta['estado'] !== 'CERRADA') {
            throw ValidationException::campo('destinatario', 'Solo se envían actas cerradas.');
        }
        if ($this->requiereFirma() && $acta['pdf_firmado_ruta'] === null) {
            throw ValidationException::campo('destinatario', 'El acta debe estar firmada digitalmente por el Jefe de la OTIC antes de enviarla.');
        }
        $destinatario = mb_strtolower(trim($destinatario));
        if (filter_var($destinatario, FILTER_VALIDATE_EMAIL) === false || mb_strlen($destinatario) > 150) {
            throw ValidationException::campo('destinatario', 'Ingrese un correo electrónico válido.');
        }
        if (!CorreoService::configurado()) {
            throw ValidationException::campo('destinatario', 'El correo saliente no está configurado (variables MAIL_* del archivo .env).');
        }

        // La Jefa de la OTIC recibe copia de cada acta (salvo que ella misma sea la destinataria).
        $copia = $this->correoJefa();
        if ($copia === $destinatario) {
            $copia = null;
        }

        $token = bin2hex(random_bytes(32));
        $ahora = new DateTimeImmutable();
        $expira = $ahora->modify('+' . $this->parametros->obtenerEntero('actas_enlace_dias_vigencia', 30) . ' days');
        $pdf = (new MantenimientoService())->pdf($actaId);
        $nombreUsuario = $acta['personal_nombres'] !== null ? $acta['personal_nombres'] . ' ' . $acta['personal_apellidos'] : '';
        $vista = [
            'acta'    => $acta,
            'usuario' => $nombreUsuario,
            'enlace'  => url('actas/confirmar/' . $token),
            'expira'  => $expira->format('Y-m-d H:i:s'),
            'firmado' => $pdf['firmado'],
        ];

        $error = null;
        try {
            (new CorreoService())->enviar(
                $destinatario,
                $nombreUsuario,
                'Acta de mantenimiento N° ' . $acta['numero'] . ' - OTIC CAEN-EPG',
                View::render('emails/acta_mantenimiento', $vista, null),
                $this->textoPlano($vista),
                [['ruta' => $pdf['ruta'], 'nombre' => $pdf['nombre']]],
                $copia !== null ? [$copia] : []
            );
        } catch (RuntimeException $e) {
            $error = mb_substr($e->getMessage(), 0, 500);
        }

        $this->db->beginTransaction();
        try {
            $bloqueada = $this->actas->findForUpdate($actaId);
            $estadoActual = (string) ($bloqueada['estado_envio'] ?? 'NO_ENVIADO');
            $envioId = $this->envios->insert([
                'mantenimiento_id' => $actaId,
                'destinatario'     => $destinatario,
                'copia'            => $copia,
                'token_hash'      => hash('sha256', $token),
                'estado'           => $error === null ? 'ENVIADO' : 'ERROR',
                'adjunto_firmado'  => $pdf['firmado'] ? 1 : 0,
                'error'            => $error,
                'enviado_por'      => $usuarioId,
                'enviado_at'       => $ahora->format('Y-m-d H:i:s'),
                'expira_at'        => $expira->format('Y-m-d H:i:s'),
            ]);
            // Un reenvío no deshace una recepción ya confirmada; un fallo solo marca ERROR
            // si el acta nunca se había enviado bien.
            $nuevoEstado = match (true) {
                $estadoActual === 'RECIBIDO' => 'RECIBIDO',
                $error === null, $estadoActual === 'ENVIADO' => 'ENVIADO',
                default => 'ERROR',
            };
            if ($nuevoEstado !== $estadoActual) {
                $this->actas->update($actaId, ['estado_envio' => $nuevoEstado]);
            }
            $this->auditoria->registrar(AuditoriaService::ENVIAR, 'mantenimientos', $actaId,
                ['estado_envio' => $estadoActual],
                [
                    'estado_envio'    => $nuevoEstado,
                    'envio_id'        => $envioId,
                    'numero'          => $acta['numero'],
                    'destinatario'    => $destinatario,
                    'copia'           => $copia,
                    'adjunto_firmado'=> $pdf['firmado'],
                    'error'           => $error,
                ],
                $usuarioId
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return $error === null;
    }

    /**
     * Envío válido para la página pública de confirmación. Registra la primera apertura.
     *
     * @return array<string, mixed>
     */
    public function paraConfirmar(string $token): array
    {
        $envio = $this->envioPorToken($token, false);
        if ($envio['visto_at'] === null) {
            $this->envios->marcarVisto((int) $envio['id']);
        }

        return $envio + ['vencido' => $this->vencido($envio)];
    }

    /**
     * El usuario confirma la recepción. Devuelve false si ya estaba confirmada.
     */
    public function confirmar(string $token, string $ip, string $userAgent): bool
    {
        $this->db->beginTransaction();
        try {
            $envio = $this->envioPorToken($token, true);
            if ($envio['estado'] === 'RECIBIDO') {
                $this->db->commit();

                return false;
            }
            if ($this->vencido($envio)) {
                throw ValidationException::campo('token', 'El enlace venció. Solicite a la OTIC que le reenvíe el acta.');
            }

            $ahora = date('Y-m-d H:i:s');
            $this->envios->update((int) $envio['id'], [
                'estado'              => 'RECIBIDO',
                'visto_at'            => $envio['visto_at'] ?? $ahora,
                'recibido_at'         => $ahora,
                'recibido_ip'         => $ip,
                'recibido_user_agent' => mb_substr($userAgent, 0, 255),
            ]);
            $actaId = (int) $envio['mantenimiento_id'];
            $acta = $this->actas->findForUpdate($actaId);
            $this->actas->update($actaId, ['estado_envio' => 'RECIBIDO']);
            $this->auditoria->registrar(AuditoriaService::CONFIRMAR_RECEPCION, 'mantenimientos', $actaId,
                ['estado_envio' => $acta['estado_envio'] ?? null],
                ['estado_envio' => 'RECIBIDO', 'envio_id' => (int) $envio['id'], 'numero' => $envio['numero'], 'destinatario' => $envio['destinatario'], 'recibido_at' => $ahora],
                null,
                'confirmacion-correo'
            );
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * PDF que se muestra desde el enlace del correo.
     *
     * @return array{ruta: string, nombre: string, firmado: bool}
     */
    public function pdfDeEnlace(string $token): array
    {
        $envio = $this->envioPorToken($token, false);
        if ($envio['visto_at'] === null) {
            $this->envios->marcarVisto((int) $envio['id']);
        }

        return (new MantenimientoService())->pdf((int) $envio['mantenimiento_id']);
    }

    /**
     * Versión de texto del correo (para clientes sin HTML).
     *
     * @param array{acta: array<string, mixed>, usuario: string, enlace: string, expira: string, firmado: bool} $v
     */
    private function textoPlano(array $v): string
    {
        $a = $v['acta'];

        return implode("\n", [
            'Estimado(a) ' . ($v['usuario'] !== '' ? $v['usuario'] : 'usuario') . ':',
            '',
            'La Oficina de Tecnologías de la Información y Comunicación (OTIC) le remite el acta de mantenimiento N° '
                . $a['numero'] . ($v['firmado'] ? ', firmada digitalmente,' : '') . ' del equipo a su cargo:',
            '',
            '  Equipo:  ' . etiqueta((string) $a['equipo_tipo']) . ' ' . $a['marca'] . ' ' . $a['modelo']
                . ($a['nro_serie'] !== null ? ' (serie ' . $a['nro_serie'] . ')' : ''),
            '  Tipo:    ' . etiqueta((string) $a['tipo']),
            '  Fecha:   ' . fecha((string) $a['fecha_salida'], true),
            '  Técnico: ' . $a['tecnico_nombres'] . ' ' . $a['tecnico_apellidos'],
            '',
            'El acta va adjunta en PDF. Revísela y confirme su recepción en este enlace:',
            $v['enlace'],
            '',
            'El enlace vence el ' . fecha($v['expira'], true) . '.',
            '',
            'OTIC - ' . config('app.name'),
        ]);
    }

    /** @return array<string, mixed> */
    private function envioPorToken(string $token, bool $bloquear): array
    {
        if (preg_match('/^[a-f0-9]{64}$/', $token) !== 1) {
            throw new HttpException(404, 'El enlace no es válido.');
        }
        $envio = $this->envios->porToken(hash('sha256', $token), $bloquear);
        if ($envio === null || $envio['estado'] === 'ERROR') {
            throw new HttpException(404, 'El enlace no es válido.');
        }

        return $envio;
    }

    /** @param array<string, mixed> $envio */
    private function vencido(array $envio): bool
    {
        return $envio['estado'] !== 'RECIBIDO' && strtotime((string) $envio['expira_at']) < time();
    }
}
