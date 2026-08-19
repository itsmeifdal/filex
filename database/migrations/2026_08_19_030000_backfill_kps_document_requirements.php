<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Load the deployed file directly so stale production config caches
        // cannot prevent this one-time KPS backfill from seeing new values.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            foreach ($requirements as $code => $requirement) {
                if (! str_starts_with($code, 'KPS ')) {
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

    public function down(): void
    {
        // Intentionally irreversible so administrator corrections are never
        // erased by a rollback.
    }
};
