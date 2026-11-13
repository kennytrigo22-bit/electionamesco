<?php

namespace App\Support;

use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExcelExport
{
    /**
     * Génère un export Excel (.xls) à partir de lignes associatives, en reprenant
     * les en-têtes de la première ligne. Utilise une table HTML reconnue nativement
     * par Microsoft Excel — pas de dépendance supplémentaire. Retourne une
     * StreamedResponse : c'est le seul type de réponse que Livewire sait
     * transformer en téléchargement de fichier côté navigateur.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function download(array $rows, string $filename): StreamedResponse
    {
        $headers = empty($rows) ? [] : array_keys($rows[0]);
        $esc = fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

        return Response::streamDownload(function () use ($rows, $headers, $esc) {
            echo '<html><head><meta charset="utf-8"></head><body><table><tr>';
            foreach ($headers as $header) {
                echo '<th>'.$esc($header).'</th>';
            }
            echo '</tr>';

            foreach ($rows as $row) {
                echo '<tr>';
                foreach ($headers as $header) {
                    echo '<td>'.$esc($row[$header] ?? '').'</td>';
                }
                echo '</tr>';
            }
            echo '</table></body></html>';
        }, $filename.'.xls', [
            'Content-Type' => 'application/vnd.ms-excel; charset=utf-8',
        ]);
    }
}
