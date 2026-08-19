<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load the deployed file directly so a stale production config cache
        // cannot make this one-time backfill read an older configuration.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            foreach ($requirements as $code => $requirement) {
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

    public function down(): void
    {
        // Intentionally irreversible: values may have been corrected by an
        // administrator after deployment and must never be nulled by rollback.
    }
};
