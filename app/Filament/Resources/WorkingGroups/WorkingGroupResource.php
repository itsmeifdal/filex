<?php

namespace App\Filament\Resources\WorkingGroups;

use App\Filament\Resources\WorkingGroups\Pages\ManageWorkingGroups;
use App\Models\WorkingGroup;
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

class WorkingGroupResource extends Resource
{
    protected static ?string $model = WorkingGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Pokja';

    protected static ?string $modelLabel = 'Pokja';

    protected static ?string $pluralModelLabel = 'Pokja';

    protected static string|\UnitEnum|null $navigationGroup = 'Struktur Akreditasi';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('accreditation_group_id')->relationship('accreditationGroup', 'name')->label('Kelompok')->required()->searchable()->preload(),
            TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            Textarea::make('description')->label('Keterangan')->rows(3)->columnSpanFull(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('accreditationGroup.name')->label('Kelompok')->badge()->sortable(),
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->wrap(),
            TextColumn::make('standards_count')->counts('standards')->label('Standar'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([SelectFilter::make('accreditation_group_id')->relationship('accreditationGroup', 'name')->label('Kelompok')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageWorkingGroups::route('/')];
    }
}
