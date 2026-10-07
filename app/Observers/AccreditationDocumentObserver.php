<?php

namespace App\Observers;

use App\Models\AccreditationDocument;
use App\Services\WorkingGroupArchiveService;

class AccreditationDocumentObserver
{
    public function __construct(private WorkingGroupArchiveService $archives) {}

    public function created(AccreditationDocument $document): void
    {
        $this->forgetWorkingGroupArchive($document);
    }

    public function deleted(AccreditationDocument $document): void
    {
        $this->forgetWorkingGroupArchive($document);
    }

    private function forgetWorkingGroupArchive(AccreditationDocument $document): void
    {
        $document->loadMissing('assessmentElement.standard');
        $workingGroupId = $document->assessmentElement?->standard?->working_group_id;

        if ($workingGroupId !== null) {
            $this->archives->forgetCachedArchive($workingGroupId);
        }
    }
}
