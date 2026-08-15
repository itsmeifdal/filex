<?php

namespace Database\Seeders;

use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccreditationStructureSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $groups = [
                ['code' => 'MANAJEMEN', 'name' => 'MANAJEMEN', 'sort_order' => 1],
                ['code' => 'MEDIS', 'name' => 'MEDIS', 'sort_order' => 2],
            ];

            $standardNumber = 0;
            $elementNumber = 0;
            $workingGroupNumber = 0;

            foreach ($groups as $groupData) {
                $group = AccreditationGroup::query()->updateOrCreate(['code' => $groupData['code']], $groupData + ['is_active' => true]);

                for ($pokjaIndex = 1; $pokjaIndex <= 8; $pokjaIndex++) {
                    $workingGroupNumber++;
                    $prefix = $group->code === 'MANAJEMEN' ? 'M' : 'D';
                    $pokjaCode = $prefix.str_pad((string) $pokjaIndex, 2, '0', STR_PAD_LEFT);
                    $workingGroup = WorkingGroup::query()->updateOrCreate(['code' => $pokjaCode], [
                        'accreditation_group_id' => $group->id,
                        'name' => 'Pokja '.$group->name.' '.str_pad((string) $pokjaIndex, 2, '0', STR_PAD_LEFT),
                        'description' => 'Ganti nama dan deskripsi Pokja sesuai pedoman akreditasi rumah sakit yang berlaku.',
                        'sort_order' => $pokjaIndex,
                        'is_active' => true,
                    ]);

                    $standardsInPokja = $workingGroupNumber <= 4 ? 15 : 14;

                    for ($standardIndex = 1; $standardIndex <= $standardsInPokja; $standardIndex++) {
                        $standardNumber++;
                        $standardCode = $pokjaCode.'.S'.str_pad((string) $standardIndex, 2, '0', STR_PAD_LEFT);
                        $standard = Standard::query()->updateOrCreate(['code' => $standardCode], [
                            'working_group_id' => $workingGroup->id,
                            'title' => 'Standar '.$standardCode,
                            'description' => 'Judul sementara. Ganti dengan nomenklatur resmi melalui panel admin.',
                            'sort_order' => $standardIndex,
                            'is_active' => true,
                        ]);

                        $elementsInStandard = $standardNumber <= 123 ? 4 : 3;

                        for ($elementIndex = 1; $elementIndex <= $elementsInStandard; $elementIndex++) {
                            $elementNumber++;
                            $elementCode = $standardCode.'.EP'.$elementIndex;
                            AssessmentElement::query()->updateOrCreate(['code' => $elementCode], [
                                'standard_id' => $standard->id,
                                'description' => 'Elemen Penilaian '.$elementCode.' (ganti dengan uraian resmi).',
                                'sort_order' => $elementIndex,
                                'is_active' => true,
                            ]);
                        }
                    }
                }
            }
        });
    }
}
