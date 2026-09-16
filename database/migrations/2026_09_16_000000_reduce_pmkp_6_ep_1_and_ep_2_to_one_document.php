<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $requirements = require config_path('accreditation.php');

        DB::transaction(function () use ($requirements): void {
            foreach (['PMKP 6 / EP 1', 'PMKP 6 / EP 2'] as $code) {
                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->update([
                        'required_document_count' => $requirements['document_requirements'][$code]['count'],
                        'evidence_notes' => $requirements['document_requirements'][$code]['evidence'],
                    ]);
            }
        });
    }

    public function down(): void
    {
        // Do not restore the former multi-file requirements on rollback.
    }
};
