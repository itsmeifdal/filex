<?php

use App\Http\Controllers\DocumentDownloadController;
use App\Http\Controllers\DocumentPreviewController;
use App\Http\Controllers\GoogleDriveOAuthController;
use App\Http\Controllers\PublicDocumentExportController;
use App\Http\Controllers\UploadProgressChartController;
use App\Http\Controllers\WorkingGroupArchiveDownloadController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'public-upload')->name('home');
Route::get('/export-monitoring-dokumen', PublicDocumentExportController::class)->name('documents.export');
Route::get('/chart', UploadProgressChartController::class)->name('chart');
Route::get('/documents/{document}/preview', DocumentPreviewController::class)->name('documents.preview');

Route::middleware('auth')->group(function () {
    Route::get('/documents/{document}/download', DocumentDownloadController::class)->name('documents.download');
    Route::get('/working-groups/{workingGroup}/archive', WorkingGroupArchiveDownloadController::class)->name('working-groups.archive.download');
    Route::get('/admin/google-drive/connect', [GoogleDriveOAuthController::class, 'redirect'])->name('google-drive.connect');
    Route::get('/admin/google-drive/callback', [GoogleDriveOAuthController::class, 'callback'])->name('google-drive.callback');
    Route::post('/admin/google-drive/disconnect', [GoogleDriveOAuthController::class, 'disconnect'])->name('google-drive.disconnect');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
