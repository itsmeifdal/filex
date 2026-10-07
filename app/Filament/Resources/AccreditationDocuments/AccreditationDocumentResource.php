<?php

namespace App\Filament\Resources\AccreditationDocuments;

use App\Filament\Resources\AccreditationDocuments\Pages\ManageAccreditationDocuments;
use App\Models\AccreditationDocument;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use App\Services\GoogleDriveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AccreditationDocumentResource extends Resource
{
    protected static ?string $model = AccreditationDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Dokumen';

    protected static ?string $modelLabel = 'dokumen';

    protected static ?string $pluralModelLabel = 'Dokumen';

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        $user = auth()->user();

        return $user?->is_active && in_array($user->role, ['admin', 'surveyor'], true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label('Status')->options([
                'pending' => 'Menunggu',
                'verified' => 'Diverifikasi',
                'rejected' => 'Perlu perbaikan',
            ])->required(),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Struktur')->schema([
                TextEntry::make('assessmentElement.standard.workingGroup.accreditationGroup.name')->label('Kelompok'),
                TextEntry::make('assessmentElement.standard.workingGroup.name')->label('Pokja'),
                TextEntry::make('assessmentElement.standard.title')->label('Standar'),
                TextEntry::make('assessmentElement.description')->label('Elemen Penilaian')->columnSpanFull(),
            ]),
            Section::make('Dokumen')->schema([
                TextEntry::make('original_name')->label('Nama file'),
                TextEntry::make('uploader_name')->label('Pengunggah'),
                TextEntry::make('uploader_unit')->label('Unit'),
                TextEntry::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                    'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan', default => 'Menunggu verifikasi',
                }),
                TextEntry::make('reviewed_at')->label('Diverifikasi pada')->dateTime('d M Y H:i')->placeholder('—'),
                TextEntry::make('reviewer.name')->label('Diverifikasi oleh')->placeholder('—'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $table = $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('assessmentElement.standard.workingGroup.code')->label('Pokja')->badge()->searchable(),
            TextColumn::make('assessmentElement.code')->label('EP')->searchable()->sortable(),
            TextColumn::make('original_name')->label('File')->searchable()->limit(45)->tooltip(fn ($record) => $record->original_name),
            TextColumn::make('uploader_name')->label('Pengunggah')->searchable()->description(fn ($record) => $record->uploader_unit),
            TextColumn::make('size')->label('Ukuran')->formatStateUsing(fn (int $state) => number_format($state / 1024 / 1024, 2).' MB'),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan', default => 'Menunggu verifikasi',
            })->color(fn (string $state) => match ($state) {
                'verified' => 'success', 'rejected' => 'danger', default => 'warning',
            }),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(['pending' => 'Menunggu verifikasi', 'verified' => 'Terverifikasi', 'rejected' => 'Perlu perbaikan']),
            Filter::make('accreditation_structure')
                ->label('Struktur Akreditasi')
                ->form([
                    Select::make('working_group_id')
                        ->label('Pokja')
                        ->options(fn (): array => WorkingGroup::query()
                            ->where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('code')
                            ->get(['id', 'code', 'name'])
                            ->mapWithKeys(fn (WorkingGroup $workingGroup): array => [
                                $workingGroup->id => "{$workingGroup->code} — {$workingGroup->name}",
                            ])
                            ->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('standard_id', null);
                            $set('assessment_element_id', null);
                        }),
                    Select::make('standard_id')
                        ->label('Standar')
                        ->options(function (Get $get): array {
                            $workingGroupId = $get('working_group_id');

                            if (blank($workingGroupId)) {
                                return [];
                            }

                            return Standard::query()
                                ->where('is_active', true)
                                ->where('working_group_id', $workingGroupId)
                                ->orderBy('sort_order')
                                ->orderBy('code')
                                ->get(['id', 'code', 'title'])
                                ->mapWithKeys(fn (Standard $standard): array => [
                                    $standard->id => "{$standard->code} — {$standard->title}",
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->disabled(fn (Get $get): bool => blank($get('working_group_id')))
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('assessment_element_id', null)),
                    Select::make('assessment_element_id')
                        ->label('EP')
                        ->options(function (Get $get): array {
                            $standardId = $get('standard_id');

                            if (blank($standardId)) {
                                return [];
                            }

                            return AssessmentElement::query()
                                ->where('is_active', true)
                                ->where('standard_id', $standardId)
                                ->orderBy('sort_order')
                                ->orderBy('code')
                                ->get(['id', 'code', 'description'])
                                ->mapWithKeys(fn (AssessmentElement $assessmentElement): array => [
                                    $assessmentElement->id => "{$assessmentElement->code} — {$assessmentElement->description}",
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->disabled(fn (Get $get): bool => blank($get('standard_id'))),
                ])
                ->query(function (Builder $query, array $data): void {
                    $query
                        ->when(
                            $data['working_group_id'] ?? null,
                            fn (Builder $query, int|string $workingGroupId): Builder => $query->whereHas(
                                'assessmentElement.standard',
                                fn (Builder $query): Builder => $query->where('working_group_id', $workingGroupId),
                            ),
                        )
                        ->when(
                            $data['standard_id'] ?? null,
                            fn (Builder $query, int|string $standardId): Builder => $query->whereHas(
                                'assessmentElement',
                                fn (Builder $query): Builder => $query->where('standard_id', $standardId),
                            ),
                        )
                        ->when(
                            $data['assessment_element_id'] ?? null,
                            fn (Builder $query, int|string $assessmentElementId): Builder => $query->where(
                                'assessment_element_id',
                                $assessmentElementId,
                            ),
                        );
                }),
        ]);

        if (! auth()->user()?->isAdmin()) {
            return $table;
        }

        return $table->recordActions([
            Action::make('preview')
                ->label('Preview')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (AccreditationDocument $record): string => route('documents.preview', $record))
                ->openUrlInNewTab(),
            Action::make('verify')
                ->label('Terverifikasi')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Tandai dokumen terverifikasi?')
                ->modalDescription('Status ini menandakan dokumen telah lolos verifikasi internal.')
                ->visible(fn (AccreditationDocument $record): bool => $record->status !== 'verified')
                ->action(fn (AccreditationDocument $record) => $record->update(['status' => 'verified'])),
            Action::make('needsRevision')
                ->label('Perlu perbaikan')
                ->icon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Tandai dokumen perlu perbaikan?')
                ->modalDescription('Keterangan perbaikan disampaikan pada rapat, di luar sistem.')
                ->visible(fn (AccreditationDocument $record): bool => $record->status !== 'rejected')
                ->action(fn (AccreditationDocument $record) => $record->update(['status' => 'rejected'])),
            Action::make('download')->label('Unduh')->icon(Heroicon::OutlinedArrowDownTray)->url(fn ($record) => route('documents.download', $record)),
            EditAction::make()->label('Periksa'),
            DeleteAction::make()
                ->label('Hapus file & record')
                ->before(fn ($record, GoogleDriveService $drive) => $drive->delete($record->drive_file_id)),
            Action::make('deleteRecordOnly')
                ->label('Hapus record saja')
                ->icon(Heroicon::OutlinedTrash)
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Hapus record dokumen saja?')
                ->modalDescription('Gunakan ini jika file sudah tidak ada di Google Drive. File di Drive tidak akan disentuh.')
                ->modalSubmitActionLabel('Hapus record')
                ->action(fn ($record) => $record->delete()),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccreditationDocuments::route('/')];
    }
}
