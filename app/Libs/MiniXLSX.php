<?php
namespace App\Libs;

/**
 * MiniXLSX - Lector ligero de archivos .xlsx (Excel moderno)
 * - Sin dependencias externas (solo usa ZipArchive + SimpleXML de PHP)
 * - Expande celdas combinadas (crítico para bloques tipo VACACIONES)
 * - Devuelve matriz filas[ filaIndex ][ colIndex ] = valor
 */
class MiniXLSX {

    /**
     * Parsea un archivo .xlsx y devuelve matriz de valores.
     * Las celdas combinadas se expanden a todas las celdas que cubren.
     *
     * @param string $archivo Ruta absoluta al archivo .xlsx
     * @return array Matriz [fila][columna] => valor
     * @throws \Exception Si el archivo no es válido
     */
    public static function parse($archivo) {
        if (!file_exists($archivo)) {
            throw new \Exception('Archivo no encontrado: ' . $archivo);
        }

        $zip = new \ZipArchive();
        if ($zip->open($archivo) !== true) {
            throw new \Exception('No se pudo abrir el XLSX (¿archivo dañado o es .xls antiguo?)');
        }

        // 1) Leer SharedStrings (textos compartidos)
        $shared = [];
        $ssXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($ssXml) {
            $sx = simplexml_load_string($ssXml);
            if ($sx && isset($sx->si)) {
                foreach ($sx->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = trim((string)$si->t);
                    } else {
                        // Texto con formato enriquecido (varios runs <r>)
                        $txt = '';
                        if (isset($si->r)) {
                            foreach ($si->r as $r) {
                                $txt .= (string)$r->t;
                            }
                        }
                        $shared[] = trim($txt);
                    }
                }
            }
        }

        // 2) Localizar la primera hoja (sheet1.xml)
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if (!$sheetXml) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $n = $zip->getNameIndex($i);
                if (preg_match('#^xl/worksheets/sheet\d+\.xml$#', $n)) {
                    $sheetXml = $zip->getFromName($n);
                    break;
                }
            }
        }
        if (!$sheetXml) {
            $zip->close();
            throw new \Exception('El XLSX no contiene hojas de cálculo');
        }

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml) {
            $zip->close();
            throw new \Exception('No se pudo interpretar la hoja de cálculo');
        }

        // 3) Leer todas las celdas
        $rows = [];
        if (isset($xml->sheetData->row)) {
            foreach ($xml->sheetData->row as $row) {
                $rIdx = (int)$row['r'] - 1;
                foreach ($row->c as $c) {
                    $ref = (string)$c['r'];
                    $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                    $t = (string)$c['t'];
                    if ($t === 's') {
                        $val = $shared[(int)$c->v] ?? '';
                    } elseif ($t === 'inlineStr') {
                        $val = isset($c->is->t) ? (string)$c->is->t : '';
                    } else {
                        $val = isset($c->v) ? (string)$c->v : '';
                    }
                    $rows[$rIdx][$col] = trim($val);
                }
            }
        }

        // 4) Expandir celdas combinadas (mergeCells)
        if (isset($xml->mergeCells->mergeCell)) {
            foreach ($xml->mergeCells->mergeCell as $mc) {
                $ref = (string)$mc['ref'];
                if (strpos($ref, ':') === false) continue;
                list($a, $b) = explode(':', $ref);
                $c1 = self::colIndex(preg_replace('/\d+/', '', $a));
                $r1 = (int)preg_replace('/\D/', '', $a) - 1;
                $c2 = self::colIndex(preg_replace('/\d+/', '', $b));
                $r2 = (int)preg_replace('/\D/', '', $b) - 1;
                $v = $rows[$r1][$c1] ?? '';
                for ($r = $r1; $r <= $r2; $r++) {
                    for ($c = $c1; $c <= $c2; $c++) {
                        $rows[$r][$c] = $v;
                    }
                }
            }
        }

        $zip->close();
        ksort($rows);
        return $rows;
    }

    /**
     * Convierte letras de columna (A, B, ..., Z, AA, AB...) a índice 0-based.
     * Ej: 'A' => 0, 'Z' => 25, 'AA' => 26
     */
    private static function colIndex($col) {
        $idx = 0;
        $col = strtoupper($col);
        $len = strlen($col);
        for ($i = 0; $i < $len; $i++) {
            $idx = $idx * 26 + (ord($col[$i]) - 64);
        }
        return $idx - 1;
    }
}
