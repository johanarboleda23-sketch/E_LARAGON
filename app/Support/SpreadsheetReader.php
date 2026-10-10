<?php

namespace App\Support;

use ZipArchive;

/**
 * Lector mínimo de hojas de cálculo (.xlsx/.csv/.txt) sin dependencias externas,
 * reutilizado por los distintos importadores masivos de la aplicación (PUC, terceros,
 * comprobantes contables).
 */
class SpreadsheetReader
{
    /**
     * @return array<int, array<int, string>>
     */
    public static function rows(string $path, string $extension): array
    {
        if (strtolower($extension) === 'xlsx') {
            return self::rowsFromXlsx($path);
        }

        return self::rowsFromCsv($path);
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function rowsFromCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        $rows = [];
        $firstLine = fgets($handle);
        $delimiter = str_contains((string) $firstLine, ';') ? ';' : ',';
        if ($firstLine !== false) {
            $rows[] = str_getcsv($firstLine, $delimiter);
        }

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            if (count($row) === 1 && str_contains($row[0], $delimiter)) {
                $row = str_getcsv($row[0], $delimiter);
            }
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string>>
     */
    private static function rowsFromXlsx(string $path): array
    {
        $archive = new ZipArchive;
        abort_unless($archive->open($path) === true, 422, 'No se pudo abrir el archivo Excel.');

        $sharedStrings = [];
        if (($sharedXml = $archive->getFromName('xl/sharedStrings.xml')) !== false) {
            $shared = simplexml_load_string($sharedXml);
            foreach ($shared->si as $string) {
                $sharedStrings[] = implode('', array_map('strval', $string->xpath('.//*[local-name()="t"]') ?: []));
            }
        }

        $sheetXml = $archive->getFromName('xl/worksheets/sheet1.xml');
        $archive->close();
        abort_unless($sheetXml !== false, 422, 'El archivo Excel no contiene una hoja válida.');

        $sheet = simplexml_load_string($sheetXml);
        $namespaces = $sheet->getNamespaces(true);
        $uri = $namespaces[''] ?? $namespaces['x'] ?? null;
        $sheetData = $uri ? $sheet->children($uri)->sheetData : $sheet->sheetData;

        $rows = [];
        foreach ($sheetData->children($uri) as $sheetRow) {
            $row = [];
            foreach ($sheetRow->children($uri) as $cell) {
                $value = (string) ($uri ? $cell->children($uri)->v : $cell->v);
                if ((string) ($cell['t'] ?? '') === 's') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $row[] = $value;
            }
            $rows[] = $row;
        }

        return $rows;
    }
}
