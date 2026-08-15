<?php

namespace App\Http\Controllers;

use App\Models\AccreditationDocument;
use App\Services\GoogleDriveService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentPreviewController extends Controller
{
    public function __invoke(AccreditationDocument $document, GoogleDriveService $drive): StreamedResponse
    {
        abort_unless($document->assessmentElement()->where('is_active', true)->exists(), 404);

        return $drive->preview($document->drive_file_id, $document->original_name, $document->mime_type);
    }
}
