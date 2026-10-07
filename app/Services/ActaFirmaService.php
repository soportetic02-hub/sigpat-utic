<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Models\MantenimientoModel;
use App\Models\UsuarioSistemaModel;
use Throwable;

/**
 * Firma digital del acta por el Jefe de la OTIC (usuario con puede_firmar = 1).
 *
 * Flujo actual (ReFirma PDF): descargar el acta cerrada → firmarla con el DNIe
 * en ReFirma PDF → subir el PDF firmado. El sistema verifica la firma
 * (FirmaPdfService), que el certificado sea del DNI del usuario y que el PDF
 * sea el de esta acta, y lo guarda aparte: el PDF firmado nunca se regenera.
 * La integración con Firma Perú entregará el PDF firmado a registrarFirmado().
 */
final class ActaFirmaService
{
    private const CAMPO = 'pdf_firmado';

    private MantenimientoModel $actas;
    private AuditoriaService $auditoria;
    private Database $db;

    public function __construct()
    {
        $this->actas = new MantenimientoModel();
        $this->auditoria = new AuditoriaService();
        $this->db = Database::getInstance();
    }

    /** Texto que se graba en los metadatos del PDF para reconocer el acta (MantenimientoService::generarPdf). */
    public static function marca(int $actaId): string
    {
        return 'SIGPAT-ACTA-' . $actaId . '-';
    }

    public static function puedeFirmar(?array $usuario): bool
    {
        return $usuario !== null && (int) ($usuario['puede_firmar'] ?? 0) === 1;
    }

    /**
     * Valida el archivo subido (entrada de $_FILES) y registra el acta como firmada.
     *
     * @param array<string, mixed>|null $archivo
     */
    public function firmarConArchivo(int $actaId, ?array $archivo, int $usuarioId): void
    {
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($archivo === null || $error === UPLOAD_ERR_NO_FILE) {
            throw ValidationException::campo(self::CAMPO, 'Seleccione el PDF firmado.');
        }
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw ValidationException::campo(self::CAMPO, 'El archivo supera el tamaño permitido por el servidor.');
        }
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        if ($error !== UPLOAD_ERR_OK || $tmp === '' || !is_uploaded_file($tmp)) {
            throw ValidationException::campo(self::CAMPO, 'El archivo no llegó correctamente. Intente de nuevo.');
        }
        $tamanio = (int) filesize($tmp);
        if ($tamanio <= 0 || $tamanio > FirmaPdfService::TAMANIO_MAXIMO) {
            throw ValidationException::campo(self::CAMPO, 'El PDF firmado supera el máximo de 15 MB.');
        }
        if ((string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) !== 'application/pdf') {
            throw ValidationException::campo(self::CAMPO, 'Solo se acepta un archivo PDF.');
        }

        $this->registrarFirmado($actaId, (string) file_get_contents($tmp), $usuarioId);
    }

    /** Verifica y guarda el PDF firmado de un acta CERRADA. */
    public function registrarFirmado(int $actaId, string $pdf, int $usuarioId): void
    {
        $usuario = (new UsuarioSistemaModel())->find($usuarioId);
        if (!self::puedeFirmar($usuario)) {
            throw new HttpException(403, 'Su usuario no está autorizado para firmar actas.');
        }

        $acta = $this->actas->find($actaId);
        if ($acta === null) {
            throw new HttpException(404, 'El acta no existe.');
        }
        if ($acta['estado'] !== 'CERRADA') {
            throw ValidationException::campo(self::CAMPO, 'Solo se firman actas cerradas.');
        }
        if ($acta['pdf_firmado_ruta'] !== null) {
            throw ValidationException::campo(self::CAMPO, 'El acta ya está firmada.');
        }

        $firma = (new FirmaPdfService())->verificar($pdf);
        if ($usuario['dni'] !== null && $firma['dni'] !== null && $firma['dni'] !== $usuario['dni']) {
            throw ValidationException::campo(self::CAMPO, sprintf(
                'El certificado de la firma pertenece al DNI %s, no al suyo (%s). Firme con su propio DNIe.',
                $firma['dni'],
                $usuario['dni']
            ));
        }

        // El PDF debe ser el de esta acta: una firma incremental conserva el original
        // al inicio del archivo; si el firmador lo reescribe, se busca la marca del acta.
        $original = (string) file_get_contents((new MantenimientoService())->pdfOriginal($actaId)['ruta']);
        if (!str_starts_with($pdf, $original) && !$this->contieneMarca($pdf, $actaId)) {
            throw ValidationException::campo(self::CAMPO, 'El PDF firmado no corresponde al acta N° ' . $acta['numero'] . '. Descargue el acta desde esta página y fírmela.');
        }

        $servicioPdf = new PdfService();
        $relativa = $servicioPdf->guardar($pdf, 'actas_firmadas', 'acta_' . $actaId . '_' . str_replace('/', '-', (string) $acta['numero']) . '_firmada_' . bin2hex(random_bytes(4)) . '.pdf');
        $sha256 = hash('sha256', $pdf);

        $this->db->beginTransaction();
        try {
            $bloqueada = $this->actas->findForUpdate($actaId);
            if ($bloqueada === null || $bloqueada['pdf_firmado_ruta'] !== null) {
                throw ValidationException::campo(self::CAMPO, 'El acta ya está firmada.');
            }
            $datos = [
                'pdf_firmado_ruta' => $relativa,
                'firmado_por'      => $usuarioId,
                'firmado_at'       => date('Y-m-d H:i:s'),
                'firma_titular'    => $firma['titular'],
                'firma_dni'        => $firma['dni'],
                'firma_sha256'     => $sha256,
            ];
            $this->actas->update($actaId, $datos);
            $this->auditoria->registrar(AuditoriaService::FIRMAR, 'mantenimientos', $actaId, null, $datos + [
                'numero'            => $acta['numero'],
                'emisor'            => $firma['emisor'],
                'certificado_hasta' => $firma['valido_hasta'],
                'firmas_en_pdf'     => $firma['firmas'],
                'cadena_verificada' => $firma['cadena_verificada'],
            ], $usuarioId);
            $this->db->commit();
        } catch (Throwable $e) {
            $this->db->rollBack();
            $ruta = $servicioPdf->rutaAbsoluta($relativa);
            if ($ruta !== null) {
                @unlink($ruta);
            }
            throw $e;
        }
    }

    /**
     * ¿El PDF contiene la marca del acta en sus metadatos? Dompdf la escribe en
     * UTF-16BE; un firmador que reescriba el archivo puede guardarla como texto o en hexadecimal.
     */
    private function contieneMarca(string $pdf, int $actaId): bool
    {
        $marca = self::marca($actaId);
        $utf16 = mb_convert_encoding($marca, 'UTF-16BE', 'UTF-8');

        return str_contains($pdf, $marca)
            || str_contains($pdf, $utf16)
            || stripos($pdf, bin2hex($utf16)) !== false;
    }
}
