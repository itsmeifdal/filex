<?php

namespace Tests\Feature;

use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\User;
use App\Models\WorkingGroup;
use Database\Seeders\AccreditationStructureSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccreditationStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_upload_page_is_available_without_an_account(): void
    {
        $this->seed(AccreditationStructureSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Pilih EP, lalu unggah dokumennya.')
            ->assertSee('KPS')
            ->assertSee('0/10')
            ->assertDontSee('Panel petugas');
    }

    public function test_structure_seeder_creates_the_required_totals(): void
    {
        $this->seed(AccreditationStructureSeeder::class);

        $this->assertSame(2, AccreditationGroup::count());
        $this->assertSame(16, WorkingGroup::count());
        $this->assertSame(228, Standard::count());
        $this->assertSame(807, AssessmentElement::count());
    }

    public function test_surveyor_can_read_but_cannot_change_structure(): void
    {
        $surveyor = User::factory()->create(['role' => 'surveyor']);
        $standard = new Standard;

        $this->assertTrue($surveyor->can('viewAny', Standard::class));
        $this->assertFalse($surveyor->can('create', Standard::class));
        $this->assertFalse($surveyor->can('update', $standard));
        $this->assertFalse($surveyor->can('update', new AssessmentElement));
    }

    public function test_admin_can_correct_an_assessment_element_document_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $element = new AssessmentElement;

        $this->assertTrue($admin->can('update', $element));

        $element->required_document_count = 2;
        $element->evidence_notes = 'Daftar institusi dan sertifikat akreditasi.';

        $this->assertSame(2, $element->required_document_count);
        $this->assertSame('Daftar institusi dan sertifikat akreditasi.', $element->evidence_notes);
    }

    public function test_only_admin_can_manage_users(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $surveyor = User::factory()->create(['role' => 'surveyor']);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($surveyor->can('viewAny', User::class));
    }
}
