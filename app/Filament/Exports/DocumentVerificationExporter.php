<?php

namespace App\Filament\Exports;

use App\Models\AccreditationDocument;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;

class DocumentVerificationExporter extends Exporter
{
    protected static ?string $model = AccreditationDocument::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('assessmentElement.standard.workingGroup.code')->label('POKJA'),
            ExportColumn::make('assessmentElement.standard.code')->label('STANDAR'),
            ExportColumn::make('assessmentElement.code')->label('EP'),
            ExportColumn::make('original_name')
                ->label('NAMA FILE')
                ->formatStateUsing(static fn (?string $state): string => self::escapeSpreadsheetFormula($state)),
            ExportColumn::make('status')
                ->label('STATUS SISTEM')
                ->formatStateUsing(static fn (string $state): string => match ($state) {
                    'verified' => 'Terverifikasi',
                    'rejected' => 'Perlu perbaikan',
                    default => 'Belum terverifikasi',
                }),
            ExportColumn::make('system_verified')
                ->label('✓ SISTEM')
                ->state(static fn (AccreditationDocument $record): string => $record->status === 'verified' ? '✓' : ''),
            ExportColumn::make('manual_verified')
                ->label('✓ MANUAL')
                ->state(static fn (): string => ''),
            ExportColumn::make('manual_notes')
                ->label('CATATAN MANUAL')
                ->state(static fn (): string => ''),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = "Daftar verifikasi dokumen selesai diekspor ({$export->successful_rows} file).";

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= " {$failedRowsCount} file gagal diekspor.";
        }

        return $body;
    }

    /**
     * @param  Builder<AccreditationDocument>  $query
     * @return Builder<AccreditationDocument>
     */
    public static function modifyQuery(Builder $query): Builder
    {
        return $query
            ->select('accreditation_documents.*')
            ->join('assessment_elements', 'assessment_elements.id', '=', 'accreditation_documents.assessment_element_id')
            ->join('standards', 'standards.id', '=', 'assessment_elements.standard_id')
            ->join('working_groups', 'working_groups.id', '=', 'standards.working_group_id')
            ->join('accreditation_groups', 'accreditation_groups.id', '=', 'working_groups.accreditation_group_id')
            ->where('accreditation_groups.is_active', true)
            ->where('working_groups.is_active', true)
            ->where('standards.is_active', true)
            ->where('assessment_elements.is_active', true)
            ->with('assessmentElement.standard.workingGroup')
            ->orderBy('accreditation_groups.sort_order')
            ->orderBy('working_groups.sort_order')
            ->orderBy('standards.sort_order')
            ->orderBy('assessment_elements.sort_order')
            ->oldest('accreditation_documents.created_at');
    }

    private static function escapeSpreadsheetFormula(?string $value): string
    {
        $value ??= '';

        return in_array($value[0] ?? null, ['=', '+', '-', '@'], true) ? "'{$value}" : $value;
    }
}
