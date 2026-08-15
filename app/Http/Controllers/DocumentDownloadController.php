<?php

namespace App\Http\Controllers;

use App\Models\AccreditationDocument;
use App\Services\GoogleDriveService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function __invoke(AccreditationDocument $document, GoogleDriveService $drive): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user->is_active && in_array($user->role, ['admin', 'surveyor'], true), 403);

        return $drive->download($document->drive_file_id, $document->original_name, $document->mime_type);
    }
}
