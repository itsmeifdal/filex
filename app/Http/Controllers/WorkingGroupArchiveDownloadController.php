<?php

namespace App\Http\Controllers;

use App\Models\WorkingGroup;
use App\Services\WorkingGroupArchiveService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class WorkingGroupArchiveDownloadController extends Controller
{
    public function __invoke(WorkingGroup $workingGroup, WorkingGroupArchiveService $archives): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless(
            $workingGroup->is_active && $user?->is_active && in_array($user->role, ['admin', 'surveyor'], true),
            403,
        );

        $archivePath = $archives->create($workingGroup);
        $fileName = $workingGroup->code.'-dokumen.zip';

        return response()->download($archivePath, $fileName, ['Content-Type' => 'application/zip']);
    }
}
