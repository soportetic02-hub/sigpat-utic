<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ParametroModel;
use PhpOffice\PhpWord\Element\Cell;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;
use RuntimeException;

/**
 * Documentos Word (.docx) editables con PHPWord. Mantiene el mismo orden de
 * secciones que la plantilla PDF del informe técnico.
 */
final class WordService
{
    private const ANCHO_UTIL = 9638; // A4 con márgenes de 2 cm, en twips

    /** Estilo de tabla con borde fino (colores de app/Config/paleta.php). */
    private function borde(): array
    {
        return ['borderSize' => 6, 'borderColor' => paleta('borde', true), 'cellMargin' => 70];
    }

    /**
     * Genera el informe técnico y lo guarda en storage/reports/<subcarpeta>.
     * Devuelve la ruta relativa a storage/reports.
     *
     * @param array{informe: array<string, mixed>, equipos: list<array<string, mixed>>, evidencias: list<array<string, mixed>>} $datos
     */
    public function generarInforme(array $datos, string $subcarpeta, string $nombreArchivo): string
    {
        Settings::setOutputEscapingEnabled(true);
        $informe = $datos['informe'];

        $word = new PhpWord();
        $word->setDefaultFontName('Arial');
        $word->setDefaultFontSize(10);
        $word->getDocInfo()->setCreator('SIGPAT-OTIC')->setTitle('Informe técnico N° ' . $informe['numero']);
        $word->addTitleStyle(1, ['bold' => true, 'size' => 10, 'color' => paleta('superficie', true)], ['shading' => ['fill' => paleta('guinda', true)], 'spaceBefore' => 160, 'spaceAfter' => 60]);

        $section = $word->addSection([
            'paperSize'    => 'A4',
            'marginTop'    => Converter::cmToTwip(2),
            'marginBottom' => Converter::cmToTwip(2),
            'marginLeft'   => Converter::cmToTwip(2),
            'marginRight'  => Converter::cmToTwip(2),
        ]);
        $section->addFooter()->addPreserveText('Página {PAGE} de {NUMPAGES}', ['size' => 8, 'color' => paleta('texto_suave', true)], ['alignment' => Jc::CENTER]);

        $this->encabezado($section, (string) $informe['numero']);
        $this->bloqueDestinatario($section, $informe);

        $section->addTitle('I. ANTECEDENTES', 1);
        $this->parrafos($section, $informe['antecedentes'] ?? null);

        $section->addTitle('II. EQUIPOS EVALUADOS', 1);
        $this->tablaEquipos($section, $datos['equipos']);

        $section->addTitle('III. EVALUACIÓN Y DIAGNÓSTICO TÉCNICO', 1);
        foreach ($datos['equipos'] as $n => $eq) {
            $this->evaluacionEquipo($section, $n + 1, $eq);
        }

        $section->addTitle('IV. ACCIÓN REQUERIDA', 1);
        $section->addText(etiqueta((string) $informe['accion_requerida']), ['bold' => true]);

        $section->addTitle('V. CONCLUSIONES', 1);
        $this->parrafos($section, $informe['conclusiones'] ?? null);

        $section->addTitle('VI. RECOMENDACIONES', 1);
        $this->parrafos($section, $informe['recomendaciones'] ?? null);

        $this->firmas($section, $informe);

        if ($datos['evidencias'] !== []) {
            $section->addPageBreak();
            $section->addTitle('ANEXO: EVIDENCIAS FOTOGRÁFICAS', 1);
            $this->anexoFotos($section, $datos['evidencias']);
        }

        $directorio = config('paths.reports') . DIRECTORY_SEPARATOR . $subcarpeta;
        if (!is_dir($directorio) && !mkdir($directorio, 0775, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio de reportes.');
        }
        $nombreArchivo = preg_replace('/[^A-Za-z0-9._\-]/', '_', $nombreArchivo) ?? 'documento.docx';
        IOFactory::createWriter($word, 'Word2007')->save($directorio . DIRECTORY_SEPARATOR . $nombreArchivo);

        return $subcarpeta . '/' . $nombreArchivo;
    }

    private function encabezado(Section $section, string $numero): void
    {
        $institucion = (string) (new ParametroModel())->obtener('institucion_nombre', 'Centro de Altos Estudios Nacionales - Escuela de Posgrado (CAEN-EPG)');
        $tabla = $section->addTable(['cellMargin' => 40]);
        $fila = $tabla->addRow();

        $celdaLogo = $fila->addCell(2200, ['valign' => 'center']);
        $logo = $this->logo();
        if ($logo !== null) {
            $celdaLogo->addImage($logo, ['height' => 45]);
        } else {
            $celdaLogo->addText('CAEN-EPG', ['bold' => true, 'size' => 12, 'color' => paleta('guinda', true)]);
            $celdaLogo->addText('OTIC', ['size' => 8]);
        }

        $celda = $fila->addCell(self::ANCHO_UTIL - 2200, ['valign' => 'center']);
        $centro = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
        $celda->addText($institucion, ['size' => 8, 'color' => paleta('texto_suave', true)], $centro);
        $celda->addText('OFICINA DE TECNOLOGÍAS DE LA INFORMACIÓN Y COMUNICACIÓN', ['bold' => true, 'size' => 8], $centro);
        $celda->addText('INFORME TÉCNICO N° ' . $numero, ['bold' => true, 'size' => 13, 'color' => paleta('guinda', true)], ['alignment' => Jc::CENTER, 'spaceBefore' => 80]);

        $section->addText('', [], ['borderBottomSize' => 12, 'borderBottomColor' => paleta('guinda', true), 'spaceAfter' => 120]);
    }

    /** @param array<string, mixed> $informe */
    private function bloqueDestinatario(Section $section, array $informe): void
    {
        $tabla = $section->addTable(['cellMargin' => 40]);
        $filas = [
            'PARA'   => [(string) $informe['para_nombre'], (string) $informe['para_cargo']],
            'DE'     => [(string) $informe['de_nombre'], (string) ($informe['de_cargo'] ?? '')],
            'ASUNTO' => [(string) $informe['asunto'], ''],
            'FECHA'  => [$this->fechaLarga((string) $informe['fecha']), ''],
        ];
        foreach ($filas as $etiqueta => [$principal, $secundario]) {
            $fila = $tabla->addRow();
            $fila->addCell(1500)->addText($etiqueta, ['bold' => true]);
            $fila->addCell(300)->addText(':');
            $celda = $fila->addCell(self::ANCHO_UTIL - 1800);
            $celda->addText($principal, ['bold' => $etiqueta === 'PARA' || $etiqueta === 'DE'], ['spaceAfter' => 0]);
            if ($secundario !== '') {
                $celda->addText($secundario, ['size' => 9, 'color' => paleta('texto_suave', true)], ['spaceAfter' => 0]);
            }
        }
        $section->addText('', [], ['borderBottomSize' => 6, 'borderBottomColor' => paleta('borde', true), 'spaceAfter' => 60]);
    }

    /** @param list<array<string, mixed>> $equipos */
    private function tablaEquipos(Section $section, array $equipos): void
    {
        $tabla = $section->addTable($this->borde());
        $anchos = [500, 1300, 1500, 2300, 2000, 2038];
        $titulos = ['N°', 'Tipo', 'Marca', 'Modelo', 'N° de serie', 'Cód. patrimonial'];
        $fila = $tabla->addRow(null, ['tblHeader' => true]);
        foreach ($titulos as $i => $titulo) {
            $fila->addCell($anchos[$i], ['bgColor' => paleta('celda', true)])->addText($titulo, ['bold' => true, 'size' => 9]);
        }
        foreach ($equipos as $n => $eq) {
            $fila = $tabla->addRow(null, ['cantSplit' => true]);
            $valores = [(string) ($n + 1), etiqueta((string) $eq['tipo']), (string) $eq['marca'], (string) $eq['modelo'],
                (string) ($eq['nro_serie'] ?? '—'), (string) ($eq['codigo_patrimonial'] ?? '—')];
            foreach ($valores as $i => $valor) {
                $fila->addCell($anchos[$i])->addText($valor, ['size' => 9]);
            }
        }
    }

    /** @param array<string, mixed> $eq */
    private function evaluacionEquipo(Section $section, int $n, array $eq): void
    {
        $section->addText(
            sprintf('%d. %s %s %s — Serie: %s — Patrimonial: %s', $n, etiqueta((string) $eq['tipo']), $eq['marca'], $eq['modelo'], $eq['nro_serie'] ?? '—', $eq['codigo_patrimonial'] ?? '—'),
            ['bold' => true, 'size' => 9.5],
            ['spaceBefore' => 120, 'spaceAfter' => 40, 'keepNext' => true]
        );
        $tabla = $section->addTable($this->borde());
        foreach (['Características' => 'caracteristicas', 'Estado funcional' => 'estado_funcional', 'Diagnóstico técnico' => 'diagnostico'] as $titulo => $campo) {
            $fila = $tabla->addRow(null, ['cantSplit' => true]);
            $fila->addCell(2400, ['bgColor' => paleta('celda', true)])->addText($titulo, ['bold' => true, 'size' => 9]);
            $this->textoEnCelda($fila->addCell(self::ANCHO_UTIL - 2400), $eq[$campo] ?? null);
        }
    }

    /** @param array<string, mixed> $informe */
    private function firmas(Section $section, array $informe): void
    {
        $section->addTextBreak(2);
        $tabla = $section->addTable(['cellMargin' => 80]);
        $fila = $tabla->addRow(null, ['cantSplit' => true]);
        $centro = ['alignment' => Jc::CENTER, 'spaceAfter' => 0];
        $bloques = [
            ['Elaborado por', (string) $informe['de_nombre'], (string) ($informe['de_cargo'] ?? '')],
            ['Recibido / V°B°', (string) $informe['para_nombre'], (string) $informe['para_cargo']],
        ];
        foreach ($bloques as $i => [$titulo, $nombre, $cargo]) {
            if ($i === 1) {
                $fila->addCell(638);
            }
            $celda = $fila->addCell(4500);
            $celda->addTextBreak(2);
            $celda->addText('______________________________', [], $centro);
            $celda->addText($nombre, ['bold' => true, 'size' => 9], $centro);
            $celda->addText($cargo, ['size' => 8], $centro);
            $celda->addText($titulo, ['size' => 8, 'color' => paleta('texto_suave', true)], $centro);
        }
    }

    /** @param list<array<string, mixed>> $evidencias */
    private function anexoFotos(Section $section, array $evidencias): void
    {
        $tabla = $section->addTable($this->borde());
        foreach (array_chunk($evidencias, 2) as $par) {
            $fila = $tabla->addRow(null, ['cantSplit' => true]);
            foreach ($par as $foto) {
                $celda = $fila->addCell(intdiv(self::ANCHO_UTIL, 2), ['valign' => 'top']);
                [$ancho, $alto] = $this->medidasFoto((string) $foto['ruta'], 220, 190);
                $celda->addImage((string) $foto['ruta'], ['width' => $ancho, 'height' => $alto, 'alignment' => Jc::CENTER]);
                $leyenda = trim(($foto['descripcion'] ?? '') . ($foto['marca'] !== null ? ' (' . $foto['marca'] . ' ' . $foto['modelo'] . ')' : ''));
                $celda->addText($leyenda !== '' ? $leyenda : 'Evidencia', ['size' => 8], ['alignment' => Jc::CENTER]);
            }
            if (count($par) === 1) {
                $fila->addCell(intdiv(self::ANCHO_UTIL, 2));
            }
        }
    }

    /** Texto con saltos de línea como párrafos. */
    private function parrafos(Section $section, ?string $texto): void
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            $section->addText('—');

            return;
        }
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $section->addText($linea, [], ['alignment' => Jc::BOTH, 'spaceAfter' => 60]);
        }
    }

    private function textoEnCelda(Cell $celda, mixed $texto): void
    {
        $texto = trim((string) $texto);
        if ($texto === '') {
            $celda->addText('—', ['size' => 9]);

            return;
        }
        foreach (preg_split('/\R/u', $texto) ?: [] as $linea) {
            $celda->addText($linea, ['size' => 9], ['spaceAfter' => 0]);
        }
    }

    /**
     * Ajusta la foto dentro de un rectángulo (en puntos) conservando la proporción.
     *
     * @return array{0: int, 1: int}
     */
    private function medidasFoto(string $ruta, int $anchoMax, int $altoMax): array
    {
        $info = @getimagesize($ruta);
        if ($info === false || $info[0] < 1 || $info[1] < 1) {
            return [$anchoMax, $altoMax];
        }
        $factor = min($anchoMax / $info[0], $altoMax / $info[1]);

        return [max(1, (int) round($info[0] * $factor)), max(1, (int) round($info[1] * $factor))];
    }

    private function logo(): ?string
    {
        foreach (['logo-caen.png', 'logo-caen.jpg', 'logo-caen.jpeg'] as $archivo) {
            $ruta = base_path('public/assets/img/' . $archivo);
            if (is_file($ruta)) {
                return $ruta;
            }
        }

        return null;
    }

    private function fechaLarga(string $fecha): string
    {
        $meses = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
        $ts = strtotime($fecha);
        if ($ts === false) {
            return $fecha;
        }

        return sprintf('%d de %s de %s', (int) date('j', $ts), $meses[(int) date('n', $ts) - 1], date('Y', $ts));
    }
}
