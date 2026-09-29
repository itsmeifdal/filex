<?php

namespace App\Http\Controllers;

use App\Models\AssessmentElement;
use App\Models\WorkingGroup;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicDocumentExportController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $workingGroups = WorkingGroup::query()
            ->where('is_active', true)
            ->whereHas('accreditationGroup', fn ($query) => $query->where('is_active', true))
            ->with([
                'standards' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with([
                        'assessmentElements' => fn ($query) => $query
                            ->where('is_active', true)
                            ->whereNotNull('required_document_count')
                            ->where('required_document_count', '>', 0)
                            ->with(['documents' => fn ($query) => $query->oldest()]),
                    ]),
            ])
            ->orderBy('sort_order')
            ->get();

        $summaryRows = [];
        $detailRows = [];

        foreach ($workingGroups as $workingGroup) {
            $required = 0;
            $uploaded = 0;
            $elements = 0;

            foreach ($workingGroup->standards as $standard) {
                foreach ($standard->assessmentElements as $element) {
                    $requiredCount = max(0, (int) $element->required_document_count);
                    $documentCount = $element->documents->count();
                    $required += $requiredCount;
                    $uploaded += min($documentCount, $requiredCount);
                    $elements++;

                    foreach ($this->detailRowsForElement($workingGroup->code, $standard->code, $element) as $row) {
                        $detailRows[] = $row;
                    }
                }
            }

            $summaryRows[] = [
                $workingGroup->code,
                $workingGroup->name,
                $workingGroup->standards->count(),
                $elements,
                $required,
                $uploaded,
                max(0, $required - $uploaded),
            ];
        }

        $spreadsheet = new Spreadsheet;
        $spreadsheet->removeSheetByIndex(0);

        $this->makeSummarySheet($spreadsheet, $summaryRows);
        $this->makeDetailSheet($spreadsheet, $detailRows);
        $this->makeMissingSheet($spreadsheet, $detailRows);

        $fileName = 'monitoring-upload-dokumen-'.now()->format('Y-m-d-His').'.xlsx';

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array<int, array{string, string, string, string, string, string}> */
    private function detailRowsForElement(string $pokja, string $standard, AssessmentElement $element): array
    {
        $requiredCount = max(0, (int) $element->required_document_count);
        $requirements = $this->splitRequirementNotes($element->evidence_notes, $requiredCount);
        $documents = $element->documents->values();
        $rows = [];

        for ($index = 0; $index < $requiredCount; $index++) {
            $document = $documents->get($index);
            $rows[] = [
                $pokja,
                $standard,
                sprintf('%s - %d Dokumen', $element->code, $requiredCount),
                $requirements[$index] ?? sprintf('Dokumen %d', $index + 1),
                $document->original_name ?? '-',
                $document ? 'Checked' : 'UnCheck',
            ];
        }

        foreach ($documents->slice($requiredCount) as $document) {
            $rows[] = [
                $pokja,
                $standard,
                sprintf('%s - %d Dokumen', $element->code, $requiredCount),
                'Dokumen tambahan',
                $document->original_name,
                'Checked',
            ];
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function splitRequirementNotes(?string $notes, int $requiredCount): array
    {
        $notes = trim((string) $notes);

        if ($requiredCount === 1 || $notes === '') {
            return [$notes !== '' ? $notes : 'Dokumen 1'];
        }

        $notes = preg_replace('/^\s*\[[^\]]+\]\s*/u', '', $notes) ?? $notes;
        $parts = preg_split('/;\s*(?=(?:\[[^\]]+\]\s*)?\d+\.\s*)/u', $notes) ?: [];
        $items = array_values(array_filter(array_map(
            static fn (string $part): string => preg_replace('/^\s*(?:\[[^\]]+\]\s*)?\d+\.\s*/u', '', trim($part)) ?? trim($part),
            $parts,
        )));

        while (count($items) < $requiredCount) {
            $items[] = sprintf('Dokumen %d', count($items) + 1);
        }

        return array_slice($items, 0, $requiredCount);
    }

    /** @param array<int, array<int, int|string>> $rows */
    private function makeSummarySheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Ringkasan');
        $sheet->fromArray(['Ringkasan Monitoring Upload Dokumen'], null, 'A1');
        $sheet->mergeCells('A1:G1');
        $sheet->fromArray(['Diekspor pada '.now()->format('d-m-Y H:i')], null, 'A2');
        $sheet->mergeCells('A2:G2');
        $sheet->fromArray(['POKJA', 'NAMA POKJA', 'STANDAR', 'EP', 'DOKUMEN DIMINTA', 'CHECKED', 'UNCHECK'], null, 'A4');
        $sheet->fromArray($rows, null, 'A5');
        $this->styleSheet($sheet, 'A1:G'.max(5, count($rows) + 4), 'A4:G4');
        $sheet->setAutoFilter('A4:G'.max(5, count($rows) + 4));
        $sheet->getStyle('C5:G'.max(5, count($rows) + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 14, 'B' => 24, 'C' => 12, 'D' => 10, 'E' => 20, 'F' => 13, 'G' => 13] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
    }

    /** @param array<int, array{string, string, string, string, string, string}> $rows */
    private function makeDetailSheet(Spreadsheet $spreadsheet, array $rows): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detail per POKJA');
        $sheet->fromArray(['Detail Upload Dokumen per POKJA'], null, 'A1');
        $sheet->mergeCells('A1:F1');
        $sheet->fromArray(['Satu baris menunjukkan satu dokumen yang diminta.'], null, 'A2');
        $sheet->mergeCells('A2:F2');
        $sheet->fromArray(['POKJA', 'STANDAR', 'EP', 'PENJELASAN', 'FILE YANG DIUNGGAH', 'STATUS'], null, 'A4');
        $sheet->fromArray($rows, null, 'A5');
        $this->styleSheet($sheet, 'A1:F'.max(5, count($rows) + 4), 'A4:F4');
        $sheet->setAutoFilter('A4:F'.max(5, count($rows) + 4));
        $sheet->getStyle('A5:C'.max(5, count($rows) + 4))->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('F5:F'.max(5, count($rows) + 4))->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        foreach (['A' => 13, 'B' => 15, 'C' => 23, 'D' => 65, 'E' => 38, 'F' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $this->applyStatusColors($sheet, 5, max(5, count($rows) + 4));
    }

    /** @param array<int, array{string, string, string, string, string, string}> $detailRows */
    private function makeMissingSheet(Spreadsheet $spreadsheet, array $detailRows): void
    {
        $rows = array_values(array_filter($detailRows, static fn (array $row): bool => $row[5] === 'UnCheck'));
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Belum Upload');
        $sheet->fromArray(['Daftar Dokumen Belum Upload'], null, 'A1');
        $sheet->mergeCells('A1:F1');
        $sheet->fromArray(['Gunakan daftar ini untuk tindak lanjut ke POKJA.'], null, 'A2');
        $sheet->mergeCells('A2:F2');
        $sheet->fromArray(['POKJA', 'STANDAR', 'EP', 'PENJELASAN', 'FILE YANG DIUNGGAH', 'STATUS'], null, 'A4');
        $sheet->fromArray($rows, null, 'A5');
        $this->styleSheet($sheet, 'A1:F'.max(5, count($rows) + 4), 'A4:F4');
        $sheet->setAutoFilter('A4:F'.max(5, count($rows) + 4));
        foreach (['A' => 13, 'B' => 15, 'C' => 23, 'D' => 65, 'E' => 38, 'F' => 14] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }
        $this->applyStatusColors($sheet, 5, max(5, count($rows) + 4));
    }

    private function styleSheet(Worksheet $sheet, string $range, string $headerRange): void
    {
        $sheet->freezePane('A5');
        $sheet->setAutoFilter($headerRange);
        $sheet->getStyle($range)->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(15)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0F5B54');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('52655F');
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('087D72');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D7E2DD');
        $sheet->getRowDimension(1)->setRowHeight(24);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(4)->setRowHeight(28);
    }

    private function applyStatusColors(Worksheet $sheet, int $startRow, int $endRow): void
    {
        for ($row = $startRow; $row <= $endRow; $row++) {
            $cell = $sheet->getCell("F{$row}");

            if ($cell->getValue() === null) {
                continue;
            }

            $isChecked = $cell->getValue() === 'Checked';
            $sheet->getStyle("F{$row}")->getFont()->setBold(true)->getColor()->setRGB($isChecked ? '216E39' : 'A63D36');
            $sheet->getStyle("F{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($isChecked ? 'DCEFD9' : 'F9DEDC');
        }
    }
}
