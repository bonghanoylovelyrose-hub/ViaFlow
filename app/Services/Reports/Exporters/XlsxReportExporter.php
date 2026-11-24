<?php

namespace App\Services\Reports\Exporters;

use App\Services\Reports\ReportResult;
use App\Support\ReportValue;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class XlsxReportExporter
{
    public function download(ReportResult $result): BinaryFileResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Report');

        $columns = $result->definition->columns;
        $lastColumn = Coordinate::stringFromColumnIndex(max(1, count($columns)));
        $headerRow = 6;
        $dataRow = $headerRow + 1;

        $sheet->setCellValue('A1', $result->definition->label);
        $sheet->mergeCells('A1:'.$lastColumn.'1');
        $sheet->setCellValue('A2', $result->definition->description);
        $sheet->mergeCells('A2:'.$lastColumn.'2');
        $sheet->setCellValue(
            'A3',
            'Period: '.$result->filters->periodLabel()
            .'    |    Date basis: '.$result->definition->dateBasis
            .'    |    Category: '.$result->definition->category,
        );
        $sheet->mergeCells('A3:'.$lastColumn.'3');
        $sheet->setCellValue(
            'A4',
            'Generated: '.now()->format('M d, Y h:i A').'    |    ViaFlow Reports Center',
        );
        $sheet->mergeCells('A4:'.$lastColumn.'4');

        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16)->getColor()->setARGB('FF0F4C81');
        $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setARGB('FF475569');
        $sheet->getStyle('A3:A4')->getFont()->setSize(9)->getColor()->setARGB('FF64748B');
        $sheet->getStyle('A1:A4')->getAlignment()->setWrapText(true);

        foreach (array_values($columns) as $index => $column) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $sheet->setCellValue($letter.$headerRow, $column['label']);
        }

        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['argb' => 'FFFFFFFF'],
                'size' => 9,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FF0F4C81'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FF94A3B8'],
                ],
            ],
        ];

        $sheet->getStyle('A'.$headerRow.':'.$lastColumn.$headerRow)->applyFromArray($headerStyle);
        $sheet->getRowDimension($headerRow)->setRowHeight(30);

        $rowNumber = $dataRow;

        foreach ($result->rows as $row) {
            $columnIndex = 1;

            foreach ($columns as $key => $column) {
                $letter = Coordinate::stringFromColumnIndex($columnIndex);
                $value = ReportValue::excel($row[$key] ?? null, $column['type']);

                if (in_array($column['type'], ['money', 'number'], true) && is_numeric($value)) {
                    $sheet->setCellValueExplicit(
                        $letter.$rowNumber,
                        $value,
                        DataType::TYPE_NUMERIC,
                    );
                    $sheet->getStyle($letter.$rowNumber)
                        ->getNumberFormat()
                        ->setFormatCode('#,##0.00');
                } else {
                    // Explicit string cells prevent account numbers, notes, or
                    // descriptions from being interpreted as formulas.
                    $sheet->setCellValueExplicit(
                        $letter.$rowNumber,
                        (string) $value,
                        DataType::TYPE_STRING,
                    );
                }

                $columnIndex++;
            }

            $rowNumber++;
        }

        $lastRow = max($headerRow, $rowNumber - 1);
        $range = 'A'.$headerRow.':'.$lastColumn.$lastRow;
        $sheet->getStyle($range)->getFont()->setSize(9);
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)
            ->getColor()->setARGB('FFD1D5DB');

        foreach (array_keys($columns) as $index => $key) {
            $letter = Coordinate::stringFromColumnIndex($index + 1);
            $width = mb_strlen($columns[$key]['label']) + 3;

            foreach ($result->rows as $row) {
                $width = max(
                    $width,
                    mb_strlen(ReportValue::display($row[$key] ?? null, $columns[$key]['type'])) + 2,
                );
            }

            $sheet->getColumnDimension($letter)->setWidth(min(44, max(11, $width)));
        }

        $sheet->freezePane('A'.$dataRow);
        $sheet->setAutoFilter('A'.$headerRow.':'.$lastColumn.$lastRow);
        $sheet->getPageSetup()
            ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
            ->setFitToWidth(1)
            ->setFitToHeight(0);
        $sheet->getPageSetup()->setPrintArea('A1:'.$lastColumn.$lastRow);
        $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($headerRow, $headerRow);
        $sheet->setShowGridLines(false);

        $summarySheet = $spreadsheet->createSheet();
        $summarySheet->setTitle('Summary');
        $summarySheet->setCellValue('A1', $result->definition->label.' — Summary');
        $summarySheet->mergeCells('A1:B1');
        $summarySheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setARGB('FF0F4C81');
        $summarySheet->setCellValue(
            'A2',
            'Period: '.$result->filters->periodLabel().'    |    Generated: '.now()->format('M d, Y h:i A'),
        );
        $summarySheet->mergeCells('A2:B2');
        $summarySheet->getStyle('A2')->getFont()->setSize(9)->getColor()->setARGB('FF64748B');

        $summarySheet->setCellValue('A4', 'Metric');
        $summarySheet->setCellValue('B4', 'Value');
        $summarySheet->getStyle('A4:B4')->applyFromArray($headerStyle);

        $summaryRow = 5;

        foreach ($result->summary as $item) {
            $summarySheet->setCellValue('A'.$summaryRow, $item['label']);
            $summaryValue = ReportValue::excel($item['value'], $item['type']);

            if (in_array($item['type'], ['money', 'number'], true) && is_numeric($summaryValue)) {
                $summarySheet->setCellValueExplicit(
                    'B'.$summaryRow,
                    $summaryValue,
                    DataType::TYPE_NUMERIC,
                );
                $summarySheet->getStyle('B'.$summaryRow)
                    ->getNumberFormat()
                    ->setFormatCode('#,##0.00');
            } else {
                $summarySheet->setCellValueExplicit(
                    'B'.$summaryRow,
                    (string) $summaryValue,
                    DataType::TYPE_STRING,
                );
            }

            $summaryRow++;
        }

        $summarySheet->getStyle('A4:B'.($summaryRow - 1))
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)
            ->getColor()->setARGB('FFD1D5DB');
        $summarySheet->getColumnDimension('A')->setWidth(38);
        $summarySheet->getColumnDimension('B')->setWidth(26);
        $summarySheet->setShowGridLines(false);

        $spreadsheet->setActiveSheetIndex(0);

        $path = tempnam(sys_get_temp_dir(), 'viaflow-report-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return response()->download($path, $result->filename('xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }
}
