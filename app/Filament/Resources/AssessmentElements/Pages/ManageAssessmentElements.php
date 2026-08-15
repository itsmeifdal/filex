<?php

namespace App\Filament\Resources\AssessmentElements\Pages;

use App\Filament\Resources\AssessmentElements\AssessmentElementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageAssessmentElements extends ManageRecords
{
    protected static string $resource = AssessmentElementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
