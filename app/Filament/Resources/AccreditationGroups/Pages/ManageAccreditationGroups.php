<?php

namespace App\Filament\Resources\AccreditationGroups\Pages;

use App\Filament\Resources\AccreditationGroups\AccreditationGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAccreditationGroups extends ManageRecords
{
    protected static string $resource = AccreditationGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
