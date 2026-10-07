<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\View;
use Dompdf\Canvas;
use Dompdf\Dompdf;
use Dompdf\FontMetrics;
use Dompdf\Options;
use RuntimeException;

/**
 * Generación de PDF con Dompdf (sección 10): A4 vertical, DejaVu Sans,
 * recursos remotos deshabilitados, chroot en el proyecto e imágenes en base64.
 */
final class PdfService
{
    /** Logos institucionales buscados en public/assets/img (el primero que exista). */
    private const LOGOS = [
        'institucional' => ['logo-caen.png', 'logo-caen.jpg', 'logo-caen.jpeg'],
        'otic'          => ['logo-otic.png', 'logo-otic.jpg', 'logo-otic.jpeg'],
    ];

    /**
     * Renderiza una plantilla de app/Views/pdf y devuelve el binario del PDF.
     *
     * @param array<string, mixed> $datos
     * @param array<string, string> $info metadatos adicionales del PDF (p. ej. Keywords)
     */
    public function generar(string $plantilla, array $datos, string $titulo = 'Documento', array $info = []): string
    {
        $datos['logos'] = [
            'institucional' => $this->logo('institucional'),
            'otic'          => $this->logo('otic'),
        ];
        $datos['institucion'] = (new \App\Models\ParametroModel())->obtener(
            'institucion_nombre',
            'Centro de Altos Estudios Nacionales - Escuela de Posgrado (CAEN-EPG)'
        );
        $html = View::render('pdf/' . $plantilla, $datos, null);

        $opciones = new Options();
        $opciones->setIsRemoteEnabled(false);
        $opciones->setIsJavascriptEnabled(false);
        $opciones->setIsPhpEnabled(false);
        $opciones->setIsHtml5ParserEnabled(true);
        $opciones->setIsFontSubsettingEnabled(true);
        $opciones->setDefaultFont('DejaVu Sans');
        $opciones->setDefaultPaperSize('a4');
        $opciones->setDefaultPaperOrientation('portrait');
        $opciones->setChroot([base_path()]);
        $opciones->setTempDir($this->directorioTemporal());

        $dompdf = new Dompdf($opciones);
        $dompdf->addInfo('Title', $titulo);
        $dompdf->addInfo('Creator', 'SIGPAT-OTIC');
        foreach ($info as $clave => $valor) {
            $dompdf->addInfo($clave, $valor);
        }
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $dompdf->getCanvas()->page_script(static function (int $pagina, int $total, Canvas $canvas, FontMetrics $metricas): void {
            $fuente = $metricas->getFont('DejaVu Sans');
            $texto = sprintf('Página %d de %d', $pagina, $total);
            $ancho = $metricas->getTextWidth($texto, $fuente, 7);
            $canvas->text(($canvas->get_width() - $ancho) / 2, $canvas->get_height() - 24, $texto, $fuente, 7, [0.45, 0.45, 0.45]);
        });

        $salida = $dompdf->output();
        if ($salida === null || $salida === '') {
            throw new RuntimeException('Dompdf no generó contenido.');
        }

        return $salida;
    }

    /**
     * Guarda el PDF en storage/reports/<subcarpeta>/ y devuelve la ruta relativa a storage/reports.
     */
    public function guardar(string $contenido, string $subcarpeta, string $nombreArchivo): string
    {
        $subcarpeta = trim(preg_replace('/[^a-z0-9_\-]/i', '', $subcarpeta) ?? '', '/');
        $nombreArchivo = preg_replace('/[^A-Za-z0-9._\-]/', '_', $nombreArchivo) ?? 'documento.pdf';
        $directorio = config('paths.reports') . DIRECTORY_SEPARATOR . $subcarpeta;
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de reportes.');
        }
        $ruta = $directorio . DIRECTORY_SEPARATOR . $nombreArchivo;
        if (file_put_contents($ruta, $contenido, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el PDF.');
        }

        return $subcarpeta . '/' . $nombreArchivo;
    }

    /** Ruta absoluta de un archivo guardado, o null si no existe. */
    public function rutaAbsoluta(?string $relativa): ?string
    {
        if ($relativa === null || $relativa === '' || str_contains($relativa, '..')) {
            return null;
        }
        $ruta = config('paths.reports') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativa);

        return is_file($ruta) ? $ruta : null;
    }

    /** Imagen local como data URI base64 (para Dompdf sin acceso remoto). */
    public static function imagenBase64(string $rutaAbsoluta): ?string
    {
        if (!is_file($rutaAbsoluta) || !is_readable($rutaAbsoluta)) {
            return null;
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($rutaAbsoluta);
        if (!in_array($mime, ['image/png', 'image/jpeg'], true)) {
            return null;
        }
        $contenido = file_get_contents($rutaAbsoluta);

        return $contenido === false ? null : 'data:' . $mime . ';base64,' . base64_encode($contenido);
    }

    private function logo(string $clave): ?string
    {
        foreach (self::LOGOS[$clave] as $archivo) {
            $base64 = self::imagenBase64(base_path('public/assets/img/' . $archivo));
            if ($base64 !== null) {
                return $base64;
            }
        }

        return null;
    }

    private function directorioTemporal(): string
    {
        $dir = config('paths.reports') . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_dir($dir) && is_writable($dir) ? $dir : sys_get_temp_dir();
    }
}
