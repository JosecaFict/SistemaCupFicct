<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\Response;

/*
| ExcelExport
| --------------------------------------------------------------------------
| Genera un archivo .xlsx VALIDO sin librerias externas (un xlsx es un ZIP de
| XMLs OOXML). El ZIP se arma con el helper Zip (PHP puro + zlib), porque la
| extension `zip`/ZipArchive NO esta disponible en produccion. Se pasa una
| matriz de filas (cada celda string|int|float|null) y devuelve la descarga.
*/
class ExcelExport
{
    /** Devuelve la descarga .xlsx a partir de una matriz [filas][celdas]. */
    public static function stream(string $filename, array $matrix): Response
    {
        if (!str_ends_with(strtolower($filename), '.xlsx')) {
            $filename .= '.xlsx';
        }

        $contents = Zip::create([
            '[Content_Types].xml'         => self::contentTypes(),
            '_rels/.rels'                 => self::rels(),
            'xl/workbook.xml'             => self::workbook(),
            'xl/_rels/workbook.xml.rels'  => self::workbookRels(),
            'xl/worksheets/sheet1.xml'    => self::sheetXml($matrix),
        ]);

        return new Response($contents, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Content-Length'      => (string) strlen($contents),
        ]);
    }

    private static function sheetXml(array $matrix): string
    {
        $rowsXml = '';
        $rowNum = 1;
        foreach ($matrix as $row) {
            $cellsXml = '';
            $colIdx = 0;
            foreach ((array) $row as $value) {
                $cellsXml .= self::cellXml(self::colLetter($colIdx) . $rowNum, $value);
                $colIdx++;
            }
            $rowsXml .= '<row r="' . $rowNum . '">' . $cellsXml . '</row>';
            $rowNum++;
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . self::colsXml($matrix)
            . '<sheetData>' . $rowsXml . '</sheetData>'
            . '</worksheet>';
    }

    /**
     * Calcula el ancho de cada columna segun el texto mas largo que contiene
     * (auto-fit) y emite el bloque <cols>. Sin esto, Excel deja todas las
     * columnas con el ancho por defecto (~8) y los textos largos (nombres,
     * carreras) se ven cortados hasta ensanchar a mano.
     */
    private static function colsXml(array $matrix): string
    {
        $maxLen = [];
        foreach ($matrix as $row) {
            $colIdx = 0;
            foreach ((array) $row as $value) {
                $len = ($value === null) ? 0 : mb_strlen((string) $value);
                if (!isset($maxLen[$colIdx]) || $len > $maxLen[$colIdx]) {
                    $maxLen[$colIdx] = $len;
                }
                $colIdx++;
            }
        }
        if (!$maxLen) {
            return '';
        }

        $cols = '';
        foreach ($maxLen as $colIdx => $len) {
            // +2 de respiro; acotado entre 8 (minimo legible) y 60 (tope).
            $width = max(8, min(60, $len + 2));
            $n = $colIdx + 1; // <col> usa indices 1-based
            $cols .= '<col min="' . $n . '" max="' . $n . '" width="' . $width . '" customWidth="1"/>';
        }
        return '<cols>' . $cols . '</cols>';
    }

    private static function cellXml(string $ref, $value): string
    {
        if ($value === null || $value === '') {
            return '<c r="' . $ref . '"/>';
        }
        if (is_int($value) || is_float($value)) {
            return '<c r="' . $ref . '"><v>' . $value . '</v></c>';
        }
        $text = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8');
        return '<c r="' . $ref . '" t="inlineStr"><is><t xml:space="preserve">' . $text . '</t></is></c>';
    }

    /** Indice de columna (0-based) -> letra: 0->A, 25->Z, 26->AA. */
    private static function colLetter(int $idx): string
    {
        $letter = '';
        $idx++;
        while ($idx > 0) {
            $mod = ($idx - 1) % 26;
            $letter = chr(65 + $mod) . $letter;
            $idx = intdiv($idx - 1, 26);
        }
        return $letter;
    }

    private static function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '</Types>';
    }

    private static function rels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    private static function workbook(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Reporte" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';
    }

    private static function workbookRels(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '</Relationships>';
    }
}
