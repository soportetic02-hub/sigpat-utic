<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use RuntimeException;

/**
 * Verificación de PDF firmados digitalmente (PAdES) con ReFirma PDF o Firma Perú.
 *
 * Por cada firma del PDF (/ByteRange + /Contents) se reconstruyen los bytes
 * firmados y se verifica la firma CMS con OpenSSL: si el documento cambió
 * después de firmarse, la verificación falla. La última firma debe cubrir el
 * archivo completo (solo se admite después información de validación /DSS).
 *
 * Cadena de confianza: si storage/certificados contiene certificados raíz o
 * intermedios en formato PEM (p. ej. los de RENIEC / ECERNEP), el certificado
 * del firmante debe estar emitido por ellos. Si la carpeta está vacía se
 * verifica la integridad y se lee el titular, pero no la entidad emisora.
 */
final class FirmaPdfService
{
    public const TAMANIO_MAXIMO = 15 * 1024 * 1024;

    /** OID id-smime-ct-TSTInfo (1.2.840.113549.1.9.16.1.4) codificado en DER: sello de tiempo RFC 3161. */
    private const OID_TSTINFO = "\x06\x0B\x2A\x86\x48\x86\xF7\x0D\x01\x09\x10\x01\x04";

    private const CAMPO = 'pdf_firmado';

    /**
     * @return array{titular: ?string, dni: ?string, emisor: ?string, valido_hasta: string, firmas: int, cadena_verificada: bool}
     */
    public function verificar(string $pdf): array
    {
        if (!str_starts_with($pdf, '%PDF-')) {
            throw ValidationException::campo(self::CAMPO, 'El archivo no es un PDF válido.');
        }
        if (preg_match_all('/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $pdf, $rangos, PREG_SET_ORDER) < 1) {
            throw ValidationException::campo(self::CAMPO, 'El PDF no tiene firma digital. Fírmelo con ReFirma PDF (DNIe) y vuelva a subirlo.');
        }

        $largo = strlen($pdf);
        $autoridades = $this->autoridades();
        $firmante = null;
        $firmas = 0;
        $fin = 0;
        foreach ($rangos as $r) {
            [$a, $b, $c, $d] = [(int) $r[1], (int) $r[2], (int) $r[3], (int) $r[4]];
            if ($a !== 0 || $b <= 0 || $c <= $b || $c + $d > $largo) {
                throw ValidationException::campo(self::CAMPO, 'La estructura de la firma del PDF no es válida.');
            }
            $resultado = $this->verificarFirma($pdf, $b, $c, $d, $autoridades);
            if ($resultado !== null) {
                $firmante = $resultado;
                $firmas++;
            }
            $fin = max($fin, $c + $d);
        }

        if ($firmante === null) {
            throw ValidationException::campo(self::CAMPO, 'El PDF solo contiene sellos de tiempo, no una firma digital.');
        }
        if ($fin < $largo && !$this->soloDatosDeValidacion(substr($pdf, $fin))) {
            throw ValidationException::campo(self::CAMPO, 'El PDF fue modificado después de firmarse. Vuelva a firmar el acta original.');
        }

        return $firmante + ['firmas' => $firmas, 'cadena_verificada' => $autoridades !== []];
    }

    /**
     * Verifica una firma. Devuelve los datos del firmante, o null si es un sello de tiempo del documento.
     *
     * @param list<string> $autoridades
     * @return array{titular: ?string, dni: ?string, emisor: ?string, valido_hasta: string}|null
     */
    private function verificarFirma(string $pdf, int $b, int $c, int $d, array $autoridades): ?array
    {
        $hex = trim(substr($pdf, $b, $c - $b));
        if (!str_starts_with($hex, '<') || !str_ends_with($hex, '>')) {
            throw ValidationException::campo(self::CAMPO, 'La firma del PDF no tiene un formato válido.');
        }
        $hex = preg_replace('/\s+/', '', substr($hex, 1, -1)) ?? '';
        if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) {
            throw ValidationException::campo(self::CAMPO, 'La firma del PDF no tiene un formato válido.');
        }
        // OpenSSL lee un solo objeto DER e ignora el relleno de ceros del final.
        $der = (string) hex2bin($hex);
        $esSelloTiempo = str_contains($der, self::OID_TSTINFO);

        $temporales = [];
        try {
            $archivoDatos = $temporales[] = $this->temporal(substr($pdf, 0, $b) . substr($pdf, $c, $d));
            $archivoFirma = $temporales[] = $this->temporal($der);
            $archivoCertificados = $temporales[] = $this->temporal('');

            $ok = $this->cms($archivoDatos, $archivoFirma, $archivoCertificados, $autoridades);
            if (!$ok && $esSelloTiempo) {
                return null;
            }
            if (!$ok && $autoridades !== [] && $this->cms($archivoDatos, $archivoFirma, $archivoCertificados, [])) {
                throw ValidationException::campo(self::CAMPO, 'El certificado de la firma no fue emitido por una entidad de certificación de confianza (RENIEC).');
            }
            if (!$ok) {
                throw ValidationException::campo(self::CAMPO, 'La firma digital no es válida: el documento fue alterado o la firma está dañada.');
            }

            return $this->datosCertificado((string) file_get_contents($archivoCertificados));
        } finally {
            foreach ($temporales as $t) {
                @unlink($t);
            }
        }
    }

    /** @param list<string> $autoridades */
    private function cms(string $datos, string $firma, string $certificados, array $autoridades): bool
    {
        while (openssl_error_string() !== false) {
            // Limpia la cola de errores de OpenSSL de operaciones anteriores.
        }
        $flags = OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY | ($autoridades === [] ? OPENSSL_CMS_NOVERIFY : 0);

        return openssl_cms_verify($datos, $flags, $certificados, $autoridades, null, null, null, $firma, OPENSSL_ENCODING_DER) === true;
    }

    /**
     * Titular, DNI y vigencia del certificado del firmante.
     * El DNI se lee del serialNumber (PNOPE-12345678, DNI 12345678) o del CN (… FIR 12345678 …).
     *
     * @return array{titular: ?string, dni: ?string, emisor: ?string, valido_hasta: string}
     */
    private function datosCertificado(string $pem): array
    {
        if (preg_match('/-----BEGIN CERTIFICATE-----.+?-----END CERTIFICATE-----/s', $pem, $m) !== 1) {
            throw ValidationException::campo(self::CAMPO, 'No se pudo leer el certificado del firmante.');
        }
        $cert = openssl_x509_parse($m[0]);
        if ($cert === false) {
            throw ValidationException::campo(self::CAMPO, 'No se pudo leer el certificado del firmante.');
        }

        $ahora = time();
        if ((int) $cert['validTo_time_t'] < $ahora || (int) $cert['validFrom_time_t'] > $ahora) {
            throw ValidationException::campo(self::CAMPO, 'El certificado de la firma no está vigente (vencido o aún no válido).');
        }

        $sujeto = $cert['subject'] ?? [];
        $titular = $this->atributo($sujeto, 'CN');
        $serie = $this->atributo($sujeto, 'serialNumber');
        $dni = null;
        if ($serie !== null && preg_match('/(?<!\d)(\d{8})(?!\d)/', $serie, $s) === 1) {
            $dni = $s[1];
        } elseif ($titular !== null && preg_match('/\bFIR\s*(\d{8})\b/', $titular, $s) === 1) {
            $dni = $s[1];
        }

        return [
            'titular'      => $titular !== null ? mb_substr($titular, 0, 200) : null,
            'dni'          => $dni,
            'emisor'       => $this->atributo($cert['issuer'] ?? [], 'CN') ?? $this->atributo($cert['issuer'] ?? [], 'O'),
            'valido_hasta' => date('Y-m-d H:i:s', (int) $cert['validTo_time_t']),
        ];
    }

    /** @param array<string, string|list<string>> $nombre */
    private function atributo(array $nombre, string $clave): ?string
    {
        $valor = $nombre[$clave] ?? null;
        if (is_array($valor)) {
            $valor = $valor[0] ?? null;
        }

        return is_string($valor) && trim($valor) !== '' ? trim($valor) : null;
    }

    /**
     * Tras la última firma solo se admite una actualización incremental con
     * información de validación a largo plazo (PAdES-LT: /DSS), sin contenido nuevo.
     */
    private function soloDatosDeValidacion(string $resto): bool
    {
        return str_contains($resto, '/DSS') && !preg_match('/\/Type\s*\/(Page|Annot)\b/', $resto);
    }

    /**
     * Certificados de confianza (PEM) en storage/certificados.
     *
     * @return list<string>
     */
    private function autoridades(): array
    {
        $dir = config('paths.storage') . DIRECTORY_SEPARATOR . 'certificados';
        if (!is_dir($dir)) {
            return [];
        }
        $archivos = array_merge(glob($dir . DIRECTORY_SEPARATOR . '*.pem') ?: [], glob($dir . DIRECTORY_SEPARATOR . '*.crt') ?: []);

        return array_values(array_filter($archivos, static fn (string $f): bool => is_file($f) && str_contains((string) file_get_contents($f), '-----BEGIN CERTIFICATE-----')));
    }

    private function temporal(string $contenido): string
    {
        $ruta = tempnam(sys_get_temp_dir(), 'sgf');
        if ($ruta === false || file_put_contents($ruta, $contenido) === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal para verificar la firma.');
        }

        return $ruta;
    }
}
