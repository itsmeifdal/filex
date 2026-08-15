<?php

namespace App\Filament\Widgets;

use App\Models\AccreditationDocument;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AccreditationStats extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pokja', WorkingGroup::count())->description('Kelompok kerja akreditasi'),
            Stat::make('Standar', Standard::count())->description('Standar terdaftar'),
            Stat::make('Elemen Penilaian', AssessmentElement::count())->description('EP terdaftar'),
            Stat::make('Menunggu Verifikasi', AccreditationDocument::where('status', 'pending')->count())->color('warning'),
        ];
    }
}
