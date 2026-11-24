<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ReportResult;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

final class PdfReportExporter
{
    public function download(ReportResult $result): Response
    {
        $columnCount = count($result->definition->columns);
        $paper = $columnCount > 14 ? 'a3' : 'a4';

        return Pdf::loadView('admin.reports.pdf', [
            'result' => $result,
        ])
            ->setPaper($paper, 'landscape')
            ->download($result->filename('pdf'));
    }
}
