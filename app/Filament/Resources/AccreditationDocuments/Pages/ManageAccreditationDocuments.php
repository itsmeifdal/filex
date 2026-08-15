<?php

namespace App\Filament\Resources\AccreditationDocuments\Pages;

use App\Filament\Resources\AccreditationDocuments\AccreditationDocumentResource;
use Filament\Resources\Pages\ManageRecords;

class ManageAccreditationDocuments extends ManageRecords
{
    protected static string $resource = AccreditationDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
