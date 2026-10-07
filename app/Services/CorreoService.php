<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;

/**
 * Envío de correo por SMTP (Gmail / Google Workspace de @caen.edu.pe) con PHPMailer.
 *
 * Configuración en .env (MAIL_*). Con Gmail se usa una "contraseña de aplicación"
 * de la cuenta remitente (requiere la verificación en dos pasos activada).
 */
final class CorreoService
{
    /** true si el .env tiene los datos mínimos para enviar. */
    public static function configurado(): bool
    {
        return (string) config('mail.host') !== '' && (string) config('mail.from_address') !== '';
    }

    /**
     * Envía un correo HTML con versión de texto y adjuntos opcionales.
     * Lanza RuntimeException con un mensaje legible si el servidor lo rechaza.
     *
     * @param list<array{ruta: string, nombre: string}> $adjuntos
     * @param list<string> $copias direcciones en copia (CC)
     */
    public function enviar(string $destinatario, string $nombreDestinatario, string $asunto, string $html, string $texto, array $adjuntos = [], array $copias = []): void
    {
        if (!self::configurado()) {
            throw new RuntimeException('El correo no está configurado: complete las variables MAIL_* del archivo .env.');
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) config('mail.host');
            $mail->Port = (int) config('mail.port');
            $cifrado = (string) config('mail.encryption');
            $mail->SMTPSecure = match ($cifrado) {
                'ssl'   => PHPMailer::ENCRYPTION_SMTPS,
                'tls'   => PHPMailer::ENCRYPTION_STARTTLS,
                default => '',
            };
            $mail->SMTPAutoTLS = $cifrado !== 'none';
            $usuario = (string) config('mail.username');
            if ($usuario !== '') {
                $mail->SMTPAuth = true;
                $mail->Username = $usuario;
                $mail->Password = (string) config('mail.password');
            }
            $mail->Timeout = 20;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;

            $mail->setFrom((string) config('mail.from_address'), (string) config('mail.from_name'));
            $responderA = (string) config('mail.reply_to');
            if ($responderA !== '') {
                $mail->addReplyTo($responderA);
            }
            $mail->addAddress($destinatario, $nombreDestinatario);
            foreach ($copias as $copia) {
                $mail->addCC($copia);
            }

            $mail->isHTML(true);
            $mail->Subject = $asunto;
            $mail->Body = $html;
            $mail->AltBody = $texto;
            foreach ($adjuntos as $adjunto) {
                $mail->addAttachment($adjunto['ruta'], $adjunto['nombre'], PHPMailer::ENCODING_BASE64, 'application/pdf');
            }

            $mail->send();
        } catch (PHPMailerException $e) {
            throw new RuntimeException('Error del correo saliente: ' . $this->mensaje($mail->ErrorInfo !== '' ? $mail->ErrorInfo : $e->getMessage()), 0, $e);
        }
    }

    /** Mensaje de error de PHPMailer sin datos de la cuenta ni saltos de línea. */
    private function mensaje(string $error): string
    {
        $error = trim(preg_replace('/\s+/', ' ', $error) ?? '');
        if (stripos($error, 'authenticate') !== false || stripos($error, 'Username and Password not accepted') !== false) {
            return 'usuario o contraseña de aplicación incorrectos (MAIL_USERNAME / MAIL_PASSWORD).';
        }
        if (stripos($error, 'connect') !== false) {
            return 'no se pudo conectar con ' . config('mail.host') . ':' . config('mail.port') . ' (revise la red, el firewall o MAIL_HOST/MAIL_PORT).';
        }

        return mb_substr($error, 0, 300);
    }
}
