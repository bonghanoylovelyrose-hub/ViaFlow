<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ReportResult;
use App\Support\ReportValue;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CsvReportExporter
{
    public function download(ReportResult $result): StreamedResponse
    {
        return response()->streamDownload(function () use ($result): void {
            $handle = fopen('php://output', 'w');

            // Excel opens UTF-8 CSV correctly only with a BOM. The file remains
            // valid UTF-8 for every other consumer.
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, array_map(
                fn (array $column): string => $column['label'],
                $result->definition->columns,
            ));

            foreach ($result->rows as $row) {
                $line = [];

                foreach ($result->definition->columns as $key => $column) {
                    $line[] = ReportValue::csv($row[$key] ?? null, $column['type']);
                }

                fputcsv($handle, $line);
            }

            fclose($handle);
        }, $result->filename('csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
