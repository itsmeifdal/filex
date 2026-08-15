<?php

namespace App\Filament\Resources\Standards\Pages;

use App\Filament\Resources\Standards\StandardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStandards extends ManageRecords
{
    protected static string $resource = StandardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
