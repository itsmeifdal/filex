<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Read the deployed file directly so a stale config cache cannot hide
        // the extracted PP requirements during deployment.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            foreach ($requirements as $code => $requirement) {
                if (! str_starts_with($code, 'PP ')) {
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

    public function down(): void
    {
        // Intentionally irreversible: administrator adjustments and uploaded
        // documents must survive a rollback.
    }
};
