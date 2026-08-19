<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load the file directly so a stale config cache cannot hide the
        // extracted PAP/PKPO requirements during deployment.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            $this->ensureMissingPkpo71Element($requirements);

            foreach ($requirements as $code => $requirement) {
                if (! str_starts_with($code, 'PAP ') && ! str_starts_with($code, 'PKPO ')) {
                    continue;
                }

                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->whereNull('required_document_count')
                    ->update(['required_document_count' => $requirement['count']]);

                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->whereNull('evidence_notes')
                    ->update(['evidence_notes' => $requirement['evidence']]);
            }
        });
    }

    /** @param array<string, array{count: int, evidence: string}> $requirements */
    private function ensureMissingPkpo71Element(array $requirements): void
    {
        $standardId = DB::table('standards')->where('code', 'PKPO 7.1')->value('id');

        if ($standardId === null) {
            return;
        }

        $code = 'PKPO 7.1 / EP 3';

        if (DB::table('assessment_elements')->where('code', $code)->exists()) {
            return;
        }

        $requirement = $requirements[$code] ?? null;

        DB::table('assessment_elements')->insert([
            'standard_id' => $standardId,
            'code' => $code,
            'description' => 'EP 3',
            'required_document_count' => $requirement['count'] ?? null,
            'evidence_notes' => $requirement['evidence'] ?? null,
            'sort_order' => 3,
            'drive_folder_id' => null,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Intentionally irreversible: administrator adjustments and uploaded
        // documents must survive a rollback.
    }
};
