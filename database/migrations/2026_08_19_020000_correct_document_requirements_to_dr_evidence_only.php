<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Read the deployed file directly so stale production config caches do
        // not preserve the former denominator that included W, O, and S.
        $configuration = require config_path('accreditation.php');
        $requirements = $configuration['document_requirements'] ?? [];

        DB::transaction(function () use ($requirements): void {
            foreach ($requirements as $code => $requirement) {
                if (! str_starts_with($code, 'PPK ')
                    && ! str_starts_with($code, 'SKP ')
                    && ! str_starts_with($code, 'PMKP ')) {
                    continue;
                }

                $element = DB::table('assessment_elements')
                    ->where('code', $code)
                    ->first(['required_document_count', 'evidence_notes']);

                if ($element === null || ! $this->isFormerSystemBaseline($code, $element)) {
                    continue;
                }

                DB::table('assessment_elements')
                    ->where('code', $code)
                    ->update([
                        'required_document_count' => $requirement['count'],
                        'evidence_notes' => $requirement['evidence'],
                    ]);
            }
        });
    }

    private function isFormerSystemBaseline(string $code, object $element): bool
    {
        if ($element->required_document_count === null || $element->evidence_notes === null) {
            return true;
        }

        $notes = (string) $element->evidence_notes;

        if (str_contains($notes, '[W]') || str_contains($notes, '[O]') || str_contains($notes, '[S]')) {
            return true;
        }

        // The former PMKP 4 EP 5 note used only a D marker, but incorrectly
        // counted four comparison purposes as four separate documents.
        return $code === 'PMKP 4 / EP 5'
            && (int) $element->required_document_count === 4;
    }

    public function down(): void
    {
        // Intentionally irreversible: an administrator may correct these
        // values after deployment, so rollback must never restore stale data.
    }
};
