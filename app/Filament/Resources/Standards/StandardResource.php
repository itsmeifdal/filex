<?php

namespace App\Filament\Resources\Standards;

use App\Filament\Resources\Standards\Pages\ManageStandards;
use App\Models\Standard;
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

class StandardResource extends Resource
{
    protected static ?string $model = Standard::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Standar';

    protected static ?string $modelLabel = 'standar';

    protected static ?string $pluralModelLabel = 'Standar';

    protected static string|\UnitEnum|null $navigationGroup = 'Struktur Akreditasi';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('working_group_id')->relationship('workingGroup', 'name')->label('Pokja')->required()->searchable()->preload(),
            TextInput::make('code')->label('Kode')->required()->maxLength(50)->unique(ignoreRecord: true),
            TextInput::make('title')->label('Judul')->required()->maxLength(255)->columnSpanFull(),
            Textarea::make('description')->label('Keterangan')->rows(3)->columnSpanFull(),
            TextInput::make('sort_order')->label('Urutan')->numeric()->default(0)->required(),
            Toggle::make('is_active')->label('Aktif')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('code')->columns([
            TextColumn::make('workingGroup.code')->label('Pokja')->badge()->sortable(),
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('title')->label('Judul')->searchable()->wrap()->limit(70),
            TextColumn::make('assessment_elements_count')->counts('assessmentElements')->label('EP'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->filters([SelectFilter::make('working_group_id')->relationship('workingGroup', 'name')->label('Pokja')->searchable()->preload()])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageStandards::route('/')];
    }
}
