<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Read the deployed file directly so stale production config caches
        // cannot hide the newly extracted MFK and MRMIK requirements.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            $this->ensureMissingMfk11Elements($requirements);

            foreach ($requirements as $code => $requirement) {
                if (! str_starts_with($code, 'MFK ') && ! str_starts_with($code, 'MRMIK ')) {
                    continue;
                }

                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->whereNull('required_document_count')
                    ->update([
                        'required_document_count' => $requirement['count'],
                    ]);

                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->whereNull('evidence_notes')
                    ->update([
                        'evidence_notes' => $requirement['evidence'],
                    ]);
            }
        });
    }

    /** @param array<string, array{count: int, evidence: string}> $requirements */
    private function ensureMissingMfk11Elements(array $requirements): void
    {
        $standardId = DB::table('standards')->where('code', 'MFK 11')->value('id');

        if ($standardId === null) {
            return;
        }

        foreach (range(5, 8) as $elementNumber) {
            $code = "MFK 11 / EP {$elementNumber}";

            if (DB::table('assessment_elements')->where('code', $code)->exists()) {
                continue;
            }

            $requirement = $requirements[$code] ?? null;

            DB::table('assessment_elements')->insert([
                'standard_id' => $standardId,
                'code' => $code,
                'description' => "EP {$elementNumber}",
                'required_document_count' => $requirement['count'] ?? null,
                'evidence_notes' => $requirement['evidence'] ?? null,
                'sort_order' => $elementNumber,
                'drive_folder_id' => null,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        // Intentionally irreversible: document requirements and EP folders may
        // already contain administrator changes or uploaded documents.
    }
};
