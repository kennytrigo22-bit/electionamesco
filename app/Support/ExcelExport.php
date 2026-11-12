<?php

namespace App\Support;

use Illuminate\Http\Response;

class ExcelExport
{
    /**
     * Génère un export Excel (.xls) à partir de lignes associatives, en reprenant
     * les en-têtes de la première ligne. Utilise une table HTML reconnue nativement
     * par Microsoft Excel — pas de dépendance supplémentaire.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function download(array $rows, string $filename): Response
    {
        $headers = empty($rows) ? [] : array_keys($rows[0]);
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        $html = '<html><head><meta charset="utf-8"></head><body><table><tr>';
        foreach ($headers as $header) {
            $html .= '<th>'.$esc($header).'</th>';
        }
        $html .= '</tr>';

        foreach ($rows as $row) {
            $html .= '<tr>';
            foreach ($headers as $header) {
                $html .= '<td>'.$esc($row[$header] ?? '').'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table></body></html>';

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'.xls"',
        ]);
    }
}
