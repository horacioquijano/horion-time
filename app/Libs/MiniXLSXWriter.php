<?php
namespace App\Libs;

/**
 * MiniXLSXWriter - Generador ligero de archivos .xlsx (sin Composer)
 * Crea un XLSX válido con N hojas usando solo ZipArchive.
 */
class MiniXLSXWriter {

    /**
     * Crea un archivo .xlsx
     * @param array  $hojas    [ 'NombreHoja' => [ [celda, celda...], [fila2...], ... ], ... ]
     * @param string $destino  Ruta absoluta del archivo a crear
     * @param array  $negritas [ 'NombreHoja' => [indicesDeFilaEnNegrita], ... ]
     */
    public static function crear(array $hojas, $destino, array $negritas = []) {
        $zip = new \ZipArchive();
        if ($zip->open($destino, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('No se pudo crear el archivo XLSX');
        }

        $n     = count($hojas);
        $names = array_keys($hojas);

        // ----- [Content_Types].xml -----
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }
        $ct .= '</Types>';
        $zip->addFromString('[Content_Types].xml', $ct);

        // ----- _rels/.rels -----
        $zip->addFromString('_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        // ----- xl/workbook.xml -----
        $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
        foreach ($names as $i => $nm) {
            $wb .= '<sheet name="' . self::esc(substr($nm, 0, 31)) . '" sheetId="' . ($i + 1) . '" r:id="rId' . ($i + 1) . '"/>';
        }
        $wb .= '</sheets></workbook>';
        $zip->addFromString('xl/workbook.xml', $wb);

        // ----- xl/_rels/workbook.xml.rels -----
        $wr = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
        for ($i = 1; $i <= $n; $i++) {
            $wr .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }
        $wr .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $wr .= '</Relationships>';
        $zip->addFromString('xl/_rels/workbook.xml.rels', $wr);

        // ----- xl/styles.xml (0 = normal, 1 = negrita) -----
        $zip->addFromString('xl/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            . '</styleSheet>');

        // ----- Hojas -----
        $idx = 1;
        foreach ($hojas as $nm => $rows) {
            $bold = $negritas[$nm] ?? [];
            $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
            $rNum = 0;
            foreach ($rows as $rIdx => $cells) {
                $rNum++;
                $st = in_array($rIdx, $bold, true) ? ' s="1"' : '';
                $xml .= '<row r="' . $rNum . '">';
                $cNum = 0;
                foreach ($cells as $val) {
                    $ref = self::colName($cNum) . $rNum;
                    $cNum++;
                    if ($val === null || $val === '') continue;
                    if (is_numeric($val)) {
                        $xml .= '<c r="' . $ref . '"' . $st . '><v>' . $val . '</v></c>';
                    } else {
                        $xml .= '<c r="' . $ref . '"' . $st . ' t="inlineStr"><is><t xml:space="preserve">' . self::esc((string)$val) . '</t></is></c>';
                    }
                }
                $xml .= '</row>';
            }
            $xml .= '</sheetData></worksheet>';
            $zip->addFromString('xl/worksheets/sheet' . $idx . '.xml', $xml);
            $idx++;
        }

        $zip->close();
        return true;
    }

    /** Índice 0-based → letra de columna (0=A, 25=Z, 26=AA) */
    private static function colName($i) {
        $name = '';
        $i = (int)$i;
        while ($i >= 0) {
            $name = chr(65 + ($i % 26)) . $name;
            $i = (int)floor($i / 26) - 1;
        }
        return $name;
    }

    private static function esc($s) {
        return htmlspecialchars($s, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
