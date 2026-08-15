<?php

namespace App\Filament\Resources\AssessmentElements;

use App\Filament\Resources\AssessmentElements\Pages\ManageAssessmentElements;
use App\Models\AssessmentElement;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AssessmentElementResource extends Resource
{
    protected static ?string $model = AssessmentElement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Elemen Penilaian';

    protected static ?string $modelLabel = 'elemen penilaian';

    protected static ?string $pluralModelLabel = 'Elemen Penilaian';

    protected static string|\UnitEnum|null $navigationGroup = 'Struktur Akreditasi';

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('standard_id')->relationship('standard', 'title')->getOptionLabelFromRecordUsing(fn ($record) => $record->code.' — '.$record->title)->label('Standar')->required()->searchable(['code', 'title'])->preload(),
            TextInput::make('code')->label('Kode')->required()->maxLength(70)->unique(ignoreRecord: true),
            Textarea::make('description')->label('Deskripsi EP')->required()->rows(4)->columnSpanFull(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('code')->columns([
            TextColumn::make('standard.workingGroup.code')->label('Pokja')->badge(),
            TextColumn::make('standard.code')->label('Standar')->searchable()->sortable(),
            TextColumn::make('code')->label('Kode EP')->searchable()->sortable(),
            TextColumn::make('description')->label('Deskripsi')->searchable()->wrap()->limit(80),
            TextColumn::make('documents_count')->counts('documents')->label('Dokumen'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([SelectFilter::make('standard_id')->relationship('standard', 'code')->label('Standar')->searchable()->preload()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAssessmentElements::route('/')];
    }
}
