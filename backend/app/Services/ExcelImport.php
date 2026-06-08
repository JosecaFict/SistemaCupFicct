<?php

namespace App\Services;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/*
| ExcelImport
| --------------------------------------------------------------------------
| Lee un .xlsx (sin librerias) y devuelve una matriz de filas. Soporta:
|   - sharedStrings.xml (como guarda Excel al re-guardar el archivo)
|   - inlineStr (como genera nuestra plantilla)
|   - numeros
| Pensado para la carga de notas por Excel (CU17).
*/
class ExcelImport
{
    /** Devuelve [filas][celdas] (0-based, contiguo) del primer worksheet. */
    public static function rows(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('No se pudo abrir el archivo Excel.');
        }

        // Tabla de cadenas compartidas (si existe).
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml !== false && $ssXml !== '') {
            $ss = @simplexml_load_string($ssXml);
            if ($ss !== false) {
                foreach ($ss->si as $si) {
                    $shared[] = self::nodeText($si);
                }
            }
        }

        // Primer worksheet: sheet1.xml o el primero que aparezca.
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/[^/]+\.xml$#', $name)) {
                    $sheetXml = $zip->getFromName($name);
                    break;
                }
            }
        }
        $zip->close();

        if ($sheetXml === false || $sheetXml === '') {
            throw new RuntimeException('El Excel no tiene una hoja de calculo valida.');
        }

        $sheet = @simplexml_load_string($sheetXml);
        if ($sheet === false) {
            throw new RuntimeException('No se pudo leer la hoja del Excel.');
        }

        $matrix = [];
        foreach ($sheet->sheetData->row as $row) {
            $rowNum = (int) $row['r'];
            $cells = [];
            foreach ($row->c as $c) {
                $ref  = (string) $c['r'];
                $col  = self::colIndex(preg_replace('/\d+/', '', $ref));
                $type = (string) $c['t'];

                if ($type === 's') {
                    $idx = (int) $c->v;
                    $val = $shared[$idx] ?? null;
                } elseif ($type === 'inlineStr') {
                    $val = self::nodeText($c->is);
                } else {
                    $val = isset($c->v) ? (string) $c->v : null;
                }
                $cells[$col] = $val;
            }

            $max = $cells ? max(array_keys($cells)) : -1;
            $line = [];
            for ($i = 0; $i <= $max; $i++) {
                $line[$i] = $cells[$i] ?? null;
            }
            $matrix[$rowNum] = $line;
        }

        ksort($matrix);
        return array_values($matrix);
    }

    /** Texto de un nodo <si>/<is>: concatena <t> directos y los de <r>. */
    private static function nodeText(SimpleXMLElement $node): string
    {
        $text = '';
        if (isset($node->t)) {
            $text .= (string) $node->t;
        }
        foreach ($node->r as $r) {
            $text .= (string) $r->t;
        }
        return $text;
    }

    /** Letras de columna -> indice 0-based: A->0, Z->25, AA->26. */
    private static function colIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $n = 0;
        $len = strlen($letters);
        for ($i = 0; $i < $len; $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return $n - 1;
    }
}
