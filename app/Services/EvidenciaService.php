<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\ValidationException;
use GdImage;
use RuntimeException;

/**
 * Evidencias fotográficas (sección 7.4): solo JPG/PNG de hasta 5 MB, MIME real
 * validado con finfo, nombre aleatorio y almacenamiento en storage/evidencias
 * (fuera de public). La imagen se re-codifica con GD: corrige la orientación
 * EXIF, limita el lado mayor a 1600 px y descarta metadatos (incluida la
 * ubicación GPS) y cualquier contenido ajeno a la imagen.
 */
final class EvidenciaService
{
    public const TAMANIO_MAXIMO = 5 * 1024 * 1024;
    public const MAX_POR_ENVIO = 6;
    public const MAX_POR_INFORME = 30;
    private const LADO_MAXIMO = 1600;
    private const MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png'];

    /**
     * Valida y guarda un archivo subido. Devuelve los datos para informe_evidencias.
     *
     * @param array<string, mixed> $archivo una entrada normalizada de $_FILES
     * @return array{archivo: string, nombre_original: string, mime: string, tamanio_bytes: int}
     */
    public function guardar(array $archivo): array
    {
        $nombreOriginal = $this->nombreSeguro((string) ($archivo['name'] ?? 'foto'));
        $error = (int) ($archivo['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': ' . $this->mensajeErrorSubida($error));
        }
        $tmp = (string) ($archivo['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': el archivo no llegó correctamente.');
        }
        $tamanio = (int) filesize($tmp);
        if ($tamanio <= 0 || $tamanio > self::TAMANIO_MAXIMO) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': supera el máximo de 5 MB.');
        }

        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!isset(self::MIMES[$mime])) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': solo se aceptan imágenes JPG o PNG.');
        }
        $info = @getimagesize($tmp);
        if ($info === false || $info[0] < 1 || $info[1] < 1 || $info[0] > 12000 || $info[1] > 12000) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': la imagen está dañada o tiene dimensiones no válidas.');
        }

        $imagen = $mime === 'image/jpeg' ? @imagecreatefromjpeg($tmp) : @imagecreatefrompng($tmp);
        if (!$imagen instanceof GdImage) {
            throw ValidationException::campo('evidencias', $nombreOriginal . ': no se pudo leer la imagen.');
        }
        if ($mime === 'image/jpeg') {
            $imagen = $this->corregirOrientacion($imagen, $tmp);
        }
        $imagen = $this->reducir($imagen);

        $extension = self::MIMES[$mime];
        $nombre = bin2hex(random_bytes(16)) . '.' . $extension;
        $destino = $this->directorio() . DIRECTORY_SEPARATOR . $nombre;
        $ok = $mime === 'image/jpeg' ? imagejpeg($imagen, $destino, 85) : imagepng($imagen, $destino, 6);
        imagedestroy($imagen);
        if (!$ok || !is_file($destino)) {
            throw new RuntimeException('No se pudo guardar la evidencia en storage/evidencias.');
        }

        return [
            'archivo'         => $nombre,
            'nombre_original' => $nombreOriginal,
            'mime'            => $mime,
            'tamanio_bytes'   => (int) filesize($destino),
        ];
    }

    /** Ruta absoluta de una evidencia, o null si el nombre no es válido o no existe. */
    public function ruta(string $archivo): ?string
    {
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png)$/', $archivo) !== 1) {
            return null;
        }
        $ruta = $this->directorio() . DIRECTORY_SEPARATOR . $archivo;

        return is_file($ruta) ? $ruta : null;
    }

    public function eliminarArchivo(string $archivo): void
    {
        $ruta = $this->ruta($archivo);
        if ($ruta !== null) {
            @unlink($ruta);
        }
    }

    /**
     * Normaliza $_FILES['campo'] (subida múltiple) a una lista de archivos.
     *
     * @param array<string, mixed>|null $files
     * @return list<array<string, mixed>>
     */
    public static function normalizarMultiples(?array $files): array
    {
        if ($files === null || !isset($files['name'])) {
            return [];
        }
        if (!is_array($files['name'])) {
            return (int) ($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE ? [] : [$files];
        }
        $lista = [];
        foreach (array_keys($files['name']) as $i) {
            if ((int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $lista[] = [
                'indice'   => $i,
                'name'     => $files['name'][$i] ?? '',
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
        }

        return $lista;
    }

    private function corregirOrientacion(GdImage $imagen, string $ruta): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }
        $exif = @exif_read_data($ruta);
        $orientacion = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
        $angulo = match ($orientacion) {
            3       => 180,
            6       => -90,
            8       => 90,
            default => 0,
        };
        if ($angulo === 0) {
            return $imagen;
        }
        $rotada = imagerotate($imagen, $angulo, 0);
        if ($rotada instanceof GdImage) {
            imagedestroy($imagen);

            return $rotada;
        }

        return $imagen;
    }

    private function reducir(GdImage $imagen): GdImage
    {
        $ancho = imagesx($imagen);
        $alto = imagesy($imagen);
        $mayor = max($ancho, $alto);
        if ($mayor <= self::LADO_MAXIMO) {
            imagesavealpha($imagen, true);

            return $imagen;
        }
        $factor = self::LADO_MAXIMO / $mayor;
        $nueva = imagecreatetruecolor(max(1, (int) round($ancho * $factor)), max(1, (int) round($alto * $factor)));
        imagealphablending($nueva, false);
        imagesavealpha($nueva, true);
        imagecopyresampled($nueva, $imagen, 0, 0, 0, 0, imagesx($nueva), imagesy($nueva), $ancho, $alto);
        imagedestroy($imagen);

        return $nueva;
    }

    private function directorio(): string
    {
        $dir = (string) config('paths.evidencias');
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException('No se pudo crear storage/evidencias.');
        }

        return $dir;
    }

    private function nombreSeguro(string $nombre): string
    {
        $nombre = basename(str_replace('\\', '/', $nombre));
        $nombre = (string) preg_replace('/[\x00-\x1F\x7F]/u', '', $nombre);

        return mb_substr($nombre !== '' ? $nombre : 'foto', 0, 255);
    }

    private function mensajeErrorSubida(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'supera el tamaño permitido.',
            UPLOAD_ERR_PARTIAL                        => 'se subió de forma incompleta.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE, UPLOAD_ERR_EXTENSION => 'el servidor no pudo recibir el archivo.',
            default                                   => 'no se pudo subir.',
        };
    }
}
