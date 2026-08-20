<?php

namespace App\Filament\Resources\AccreditationDocuments;

use App\Filament\Resources\AccreditationDocuments\Pages\ManageAccreditationDocuments;
use App\Models\AccreditationDocument;
use App\Services\GoogleDriveService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AccreditationDocumentResource extends Resource
{
    protected static ?string $model = AccreditationDocument::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $navigationLabel = 'Dokumen';

    protected static ?string $modelLabel = 'dokumen';

    protected static ?string $pluralModelLabel = 'Dokumen';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('status')->label('Status')->options([
                'pending' => 'Menunggu',
                'verified' => 'Diverifikasi',
                'rejected' => 'Ditolak',
            ])->required(),
            Textarea::make('review_notes')->label('Catatan pemeriksaan')->rows(4)->columnSpanFull(),
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
                    'verified' => 'Diverifikasi', 'rejected' => 'Ditolak', default => 'Menunggu',
                }),
                TextEntry::make('review_notes')->label('Catatan')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            TextColumn::make('created_at')->label('Dikirim')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('assessmentElement.standard.workingGroup.code')->label('Pokja')->badge()->searchable(),
            TextColumn::make('assessmentElement.code')->label('EP')->searchable()->sortable(),
            TextColumn::make('original_name')->label('File')->searchable()->limit(45)->tooltip(fn ($record) => $record->original_name),
            TextColumn::make('uploader_name')->label('Pengunggah')->searchable()->description(fn ($record) => $record->uploader_unit),
            TextColumn::make('size')->label('Ukuran')->formatStateUsing(fn (int $state) => number_format($state / 1024 / 1024, 2).' MB'),
            TextColumn::make('status')->label('Status')->badge()->formatStateUsing(fn (string $state) => match ($state) {
                'verified' => 'Diverifikasi', 'rejected' => 'Ditolak', default => 'Menunggu',
            })->color(fn (string $state) => match ($state) {
                'verified' => 'success', 'rejected' => 'danger', default => 'warning',
            }),
        ])->filters([
            SelectFilter::make('status')->label('Status')->options(['pending' => 'Menunggu', 'verified' => 'Diverifikasi', 'rejected' => 'Ditolak']),
        ])->recordActions([
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
