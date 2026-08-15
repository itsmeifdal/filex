<?php

namespace App\Filament\Resources\AccreditationGroups;

use App\Filament\Resources\AccreditationGroups\Pages\ManageAccreditationGroups;
use App\Models\AccreditationGroup;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AccreditationGroupResource extends Resource
{
    protected static ?string $model = AccreditationGroup::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Kelompok';

    protected static ?string $modelLabel = 'kelompok';

    protected static ?string $pluralModelLabel = 'Kelompok';

    protected static string|\UnitEnum|null $navigationGroup = 'Struktur Akreditasi';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Kode')->required()->maxLength(30)->unique(ignoreRecord: true),
            TextInput::make('name')->label('Nama')->required()->maxLength(255),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('sort_order')->columns([
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('working_groups_count')->counts('workingGroups')->label('Pokja'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageAccreditationGroups::route('/')];
    }
}
