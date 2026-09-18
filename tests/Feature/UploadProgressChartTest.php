<?php

namespace Tests\Feature;

use App\Models\AccreditationDocument;
use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UploadProgressChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_incomplete_near_complete_group_does_not_display_as_100_percent(): void
    {
        $group = AccreditationGroup::query()->create(['code' => 'MANAJEMEN', 'name' => 'Manajemen']);
        $workingGroup = WorkingGroup::query()->create([
            'accreditation_group_id' => $group->id,
            'code' => 'PMKP',
            'name' => 'PMKP',
        ]);
        $standard = Standard::query()->create([
            'working_group_id' => $workingGroup->id,
            'code' => 'PMKP 1',
            'title' => 'Standar PMKP',
        ]);
        $element = AssessmentElement::query()->create([
            'standard_id' => $standard->id,
            'code' => 'PMKP 1 / EP 1',
            'description' => 'Bukti dokumen.',
            'required_document_count' => 54,
        ]);

        foreach (range(1, 53) as $index) {
            AccreditationDocument::query()->create([
                'assessment_element_id' => $element->id,
                'uploader_name' => 'Petugas',
                'uploader_unit' => 'PMKP',
                'original_name' => "bukti-{$index}.pdf",
                'mime_type' => 'application/pdf',
                'size' => 1024,
                'drive_file_id' => "file-{$index}",
            ]);
        }

        $this->get(route('chart'))
            ->assertOk()
            ->assertSee('98%')
            ->assertSee('(53/54 dokumen)')
            ->assertDontSee('100%</strong> (53/54 dokumen)', false);
    }
}
