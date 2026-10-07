<?php

declare(strict_types=1);

/**
 * Lector mínimo de .xlsx (ZipArchive + SimpleXML). Devuelve cada hoja como
 * [fila => [columna => texto]] con las celdas combinadas rellenadas con el
 * valor de su celda superior izquierda.
 */
final class LectorXlsx
{
    private ZipArchive $zip;
    /** @var list<string> */
    private array $compartidas = [];
    /** @var array<string, string> nombre de hoja => ruta dentro del zip */
    private array $hojas = [];

    public function __construct(string $archivo)
    {
        $this->zip = new ZipArchive();
        if ($this->zip->open($archivo) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo Excel: ' . $archivo);
        }

        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml !== false) {
            foreach ($this->xml($xml)->si as $si) {
                $texto = isset($si->t) ? (string) $si->t : '';
                foreach ($si->r as $r) {
                    $texto .= (string) $r->t;
                }
                $this->compartidas[] = $texto;
            }
        }

        $rutas = [];
        foreach ($this->xml((string) $this->zip->getFromName('xl/_rels/workbook.xml.rels'))->Relationship as $rel) {
            $destino = (string) $rel['Target'];
            $rutas[(string) $rel['Id']] = str_starts_with($destino, '/') ? ltrim($destino, '/') : 'xl/' . $destino;
        }
        foreach ($this->xml((string) $this->zip->getFromName('xl/workbook.xml'))->sheets->sheet as $hoja) {
            $rid = (string) $hoja->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $this->hojas[trim((string) $hoja['name'])] = $rutas[$rid] ?? '';
        }
    }

    /** @return list<string> */
    public function nombresHojas(): array
    {
        return array_keys($this->hojas);
    }

    /** @return array<int, array<string, string>> */
    public function hoja(string $nombre): array
    {
        $ruta = $this->hojas[$nombre] ?? null;
        if ($ruta === null || ($contenido = $this->zip->getFromName($ruta)) === false) {
            throw new RuntimeException('El Excel no tiene la hoja "' . $nombre . '".');
        }
        $xml = $this->xml($contenido);

        $celdas = [];
        foreach ($xml->sheetData->row as $fila) {
            foreach ($fila->c as $c) {
                if (preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m) !== 1) {
                    continue;
                }
                $tipo = (string) $c['t'];
                $valor = match ($tipo) {
                    's'         => $this->compartidas[(int) $c->v] ?? '',
                    'inlineStr' => (string) $c->is->t,
                    'str', 'e'  => (string) $c->v,
                    'b'         => (string) $c->v === '1' ? 'SI' : 'NO',
                    default     => self::numero((string) $c->v),
                };
                $valor = trim(str_replace("\u{00A0}", ' ', $valor));
                if ($valor !== '') {
                    $celdas[(int) $m[2]][$m[1]] = $valor;
                }
            }
        }

        if (isset($xml->mergeCells)) {
            foreach ($xml->mergeCells->mergeCell as $rango) {
                if (preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', (string) $rango['ref'], $m) !== 1) {
                    continue;
                }
                $valor = $celdas[(int) $m[2]][$m[1]] ?? null;
                if ($valor === null) {
                    continue;
                }
                for ($f = (int) $m[2]; $f <= (int) $m[4]; $f++) {
                    for ($col = $m[1]; strlen($col) < strlen($m[3]) || (strlen($col) === strlen($m[3]) && $col <= $m[3]); $col++) {
                        $celdas[$f][$col] ??= $valor;
                    }
                }
            }
        }
        ksort($celdas);

        return $celdas;
    }

    /** Números de Excel en texto sin notación científica ni ".0" (7.40836500001E11 -> 740836500001). */
    private static function numero(string $v): string
    {
        if (!is_numeric($v)) {
            return $v;
        }
        $f = (float) $v;
        if (floor($f) === $f && abs($f) < 1e15) {
            return sprintf('%.0f', $f);
        }

        return rtrim(rtrim(sprintf('%.6F', $f), '0'), '.');
    }

    private function xml(string $contenido): SimpleXMLElement
    {
        $xml = simplexml_load_string($contenido);
        if ($xml === false) {
            throw new RuntimeException('El Excel está dañado o no es un .xlsx válido.');
        }

        return $xml;
    }
}
