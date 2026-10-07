<?php

namespace App\Services;

use App\Models\WorkingGroup;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class WorkingGroupArchiveService
{
    public function __construct(private GoogleDriveService $drive) {}

    public function create(WorkingGroup $workingGroup): string
    {
        if ($cachedArchivePath = $this->cachedArchivePath($workingGroup)) {
            return $cachedArchivePath;
        }

        $workingGroup->load([
            'standards' => fn ($query) => $query
                ->where('is_active', true)
                ->with([
                    'assessmentElements' => fn ($query) => $query
                        ->where('is_active', true)
                        ->with(['documents' => fn ($query) => $query->orderBy('created_at')->orderBy('id')]),
                ]),
        ]);

        $archivePath = tempnam(sys_get_temp_dir(), 'filex-pokja-');

        if ($archivePath === false) {
            throw new RuntimeException('Arsip ZIP sementara tidak dapat dibuat.');
        }

        $zip = new ZipArchive;
        $temporaryDocumentPaths = [];
        $documentsForArchive = [];

        try {
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new RuntimeException('Arsip ZIP tidak dapat dibuka.');
            }

            $rootPath = $this->safeArchiveSegment($workingGroup->code ?: $workingGroup->name);
            $zip->addEmptyDir($rootPath);
            $archiveEntries = [$rootPath => true];

            foreach ($workingGroup->standards as $standard) {
                $standardPath = $rootPath.'/'.$this->safeArchiveSegment($standard->title ?: $standard->code);
                $this->addDirectory($zip, $archiveEntries, $standardPath);

                foreach ($standard->assessmentElements as $element) {
                    $elementPath = $standardPath.'/'.$this->safeArchiveSegment($element->description ?: $element->code);
                    $this->addDirectory($zip, $archiveEntries, $elementPath);

                    foreach ($element->documents as $document) {
                        $temporaryDocumentPath = tempnam(sys_get_temp_dir(), 'filex-document-');

                        if ($temporaryDocumentPath === false) {
                            throw new RuntimeException('File sementara untuk dokumen tidak dapat dibuat.');
                        }

                        $temporaryDocumentPaths[] = $temporaryDocumentPath;
                        $documentsForArchive[] = [
                            'file_id' => $document->drive_file_id,
                            'path' => $temporaryDocumentPath,
                            'archive_path' => $this->uniqueArchivePath(
                                $elementPath,
                                $document->original_name,
                                $archiveEntries,
                            ),
                        ];
                    }
                }
            }

            $this->drive->downloadToPaths($documentsForArchive);

            foreach ($documentsForArchive as $document) {
                if (! $zip->addFile($document['path'], $document['archive_path'])) {
                    throw new RuntimeException("Dokumen '{$document['archive_path']}' tidak dapat dimasukkan ke arsip.");
                }

                if ($this->shouldStoreWithoutCompression($document['archive_path'])) {
                    $zip->setCompressionName($document['archive_path'], ZipArchive::CM_STORE);
                }
            }

            if (! $zip->close()) {
                throw new RuntimeException('Arsip ZIP tidak dapat diselesaikan.');
            }

            return $this->storeCachedArchive($workingGroup, $archivePath);
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($archivePath);

            throw $exception;
        } finally {
            foreach ($temporaryDocumentPaths as $temporaryDocumentPath) {
                @unlink($temporaryDocumentPath);
            }
        }
    }

    public function forgetCachedArchive(int $workingGroupId): void
    {
        Storage::disk('local')->delete($this->cachePath($workingGroupId));
    }

    private function cachedArchivePath(WorkingGroup $workingGroup): ?string
    {
        $disk = Storage::disk('local');
        $cachePath = $this->cachePath($workingGroup->id);

        if (! $disk->exists($cachePath)) {
            return null;
        }

        return $disk->path($cachePath);
    }

    private function storeCachedArchive(WorkingGroup $workingGroup, string $archivePath): string
    {
        $stream = fopen($archivePath, 'r');

        if ($stream === false) {
            throw new RuntimeException('Arsip ZIP sementara tidak dapat disimpan.');
        }

        try {
            $cachePath = $this->cachePath($workingGroup->id);

            if (!Storage::disk('local')->put($cachePath, $stream)) {
                throw new RuntimeException('Arsip ZIP tidak dapat disimpan untuk unduhan berikutnya.');
            }

            return Storage::disk('local')->path($cachePath);
        } finally {
            fclose($stream);
            @unlink($archivePath);
        }
    }

    private function cachePath(int $workingGroupId): string
    {
        return "archives/working-groups/{$workingGroupId}.zip";
    }

    /** @param array<string, true> $entries */
    private function addDirectory(ZipArchive $zip, array &$entries, string $path): void
    {
        if (isset($entries[$path])) {
            return;
        }

        if (! $zip->addEmptyDir($path)) {
            throw new RuntimeException("Folder '{$path}' tidak dapat dimasukkan ke arsip.");
        }

        $entries[$path] = true;
    }

    /** @param array<string, true> $entries */
    private function uniqueArchivePath(string $directory, string $fileName, array &$entries): string
    {
        $fileName = $this->safeArchiveSegment($fileName);
        $candidate = $directory.'/'.$fileName;

        if (! isset($entries[$candidate])) {
            $entries[$candidate] = true;

            return $candidate;
        }

        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $baseName = pathinfo($fileName, PATHINFO_FILENAME);
        $suffix = 2;

        do {
            $candidate = $directory.'/'.$baseName.' ('.$suffix++.')'.($extension === '' ? '' : '.'.$extension);
        } while (isset($entries[$candidate]));

        $entries[$candidate] = true;

        return $candidate;
    }

    private function safeArchiveSegment(string $name): string
    {
        $name = preg_replace('/[\\\\\\/\x00-\x1F:*?"<>|]+/u', '-', $name) ?? '';
        $name = trim($name, ". \t");

        return $name === '' ? 'Dokumen' : $name;
    }

    private function shouldStoreWithoutCompression(string $path): bool
    {
        return in_array(mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)), [
            'docx', 'jpg', 'jpeg', 'pdf', 'png', 'pptx', 'xlsx', 'zip',
        ], true);
    }
}
