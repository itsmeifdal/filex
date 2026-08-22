<?php

namespace App\Services;

use App\Exceptions\GoogleDriveReauthorizationRequiredException;
use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\GoogleDriveSetting;
use App\Models\Standard;
use App\Models\WorkingGroup;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GoogleDriveService
{
    private const API_URL = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD_URL = 'https://www.googleapis.com/upload/drive/v3';

    public function authorizationUrl(string $state): string
    {
        $this->ensureCredentialsConfigured();

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => route('google-drive.callback'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive https://www.googleapis.com/auth/userinfo.email openid',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);
    }

    public function exchangeCode(string $code): GoogleDriveSetting
    {
        $this->ensureCredentialsConfigured();

        $response = $this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => route('google-drive.callback'),
            'grant_type' => 'authorization_code',
        ])->throw();

        $setting = GoogleDriveSetting::current();
        $setting->fill([
            'access_token' => (string) $response->json('access_token'),
            'refresh_token' => $response->json('refresh_token') ?: $setting->refresh_token,
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60),
            'reauthorization_required_at' => null,
        ])->save();

        $userInfo = $this->http()->withToken($setting->access_token)
            ->get('https://www.googleapis.com/oauth2/v2/userinfo')
            ->throw();

        $setting->update(['connected_email' => $userInfo->json('email')]);

        return $setting->refresh();
    }

    public function ensureRootFolder(): string
    {
        $setting = GoogleDriveSetting::current();
        $this->ensureConnected($setting);

        if ($setting->root_folder_id) {
            return $setting->root_folder_id;
        }

        $folderId = $this->findRootFolder();
        $setting->update(['root_folder_id' => $folderId]);

        return $folderId;
    }

    /**
     * @return array{pokja: string, folder_id: string, standards: int, assessment_elements: int, created: int, reused: int}
     */
    public function syncPokjaFolderStructure(string $pokjaCode): array
    {
        $pokjaCode = mb_strtoupper(trim($pokjaCode));
        $standards = config("accreditation.drive_structures.{$pokjaCode}");
        $groupCode = config("accreditation.pokja_groups.{$pokjaCode}");

        if (! is_array($standards) || $standards === []) {
            throw new RuntimeException("Struktur folder Pokja '{$pokjaCode}' belum dikonfigurasi secara eksplisit.");
        }

        if (! is_string($groupCode) || $groupCode === '') {
            throw new RuntimeException("Kelompok Drive untuk Pokja '{$pokjaCode}' belum dikonfigurasi secara eksplisit.");
        }

        $created = 0;
        $reused = 0;
        $assessmentElements = 0;
        $rootFolderId = $this->ensureRootFolder();
        $rootChildren = $this->listChildFolders($rootFolderId);
        $groupFolderId = $this->requireUniqueFolder($groupCode, $rootChildren, 'folder induk');
        $groupChildren = $this->listChildFolders($groupFolderId);
        $pokjaFolderId = $this->findUniqueFolderId($pokjaCode, $groupChildren);

        if ($pokjaFolderId === null) {
            $misplacedFolderId = $this->findUniqueFolderId($pokjaCode, $rootChildren);

            if ($misplacedFolderId !== null) {
                $this->moveFolder($misplacedFolderId, $rootFolderId, $groupFolderId);
                $pokjaFolderId = $misplacedFolderId;
                $groupChildren[] = ['id' => $pokjaFolderId, 'name' => $pokjaCode];
            }
        }

        if ($pokjaFolderId === null) {
            $pokjaFolderId = $this->findOrCreateFromChildren(
                $pokjaCode,
                $groupFolderId,
                $groupChildren,
                $created,
                $reused,
            );
        } else {
            $reused++;
        }
        $pokjaChildren = $this->listChildFolders($pokjaFolderId);

        foreach ($standards as $standardNumber => $elementCount) {
            if (! is_int($elementCount) || $elementCount < 1) {
                throw new RuntimeException("Jumlah EP untuk {$pokjaCode} {$standardNumber} tidak valid.");
            }

            $standardFolderId = $this->findOrCreateFromChildren(
                "{$pokjaCode} {$standardNumber}",
                $pokjaFolderId,
                $pokjaChildren,
                $created,
                $reused,
            );
            $standardChildren = $this->listChildFolders($standardFolderId);

            for ($elementNumber = 1; $elementNumber <= $elementCount; $elementNumber++) {
                $this->findOrCreateFromChildren(
                    "EP {$elementNumber}",
                    $standardFolderId,
                    $standardChildren,
                    $created,
                    $reused,
                );
                $assessmentElements++;
            }
        }

        return [
            'pokja' => $pokjaCode,
            'folder_id' => $pokjaFolderId,
            'standards' => count($standards),
            'assessment_elements' => $assessmentElements,
            'created' => $created,
            'reused' => $reused,
        ];
    }

    /** @return array{root: string, groups: int, working_groups: int, standards: int, assessment_elements: int} */
    public function syncDatabaseStructureFromDrive(): array
    {
        $rootFolderId = $this->ensureRootFolder();
        $childrenByParent = [];

        foreach ($this->listAllFolders() as $folder) {
            foreach ($folder['parents'] as $parentId) {
                $childrenByParent[$parentId][] = ['id' => $folder['id'], 'name' => $folder['name']];
            }
        }

        $rootChildren = $childrenByParent[$rootFolderId] ?? [];
        $counts = ['groups' => 0, 'working_groups' => 0, 'standards' => 0, 'assessment_elements' => 0];

        DB::transaction(function () use ($childrenByParent, $rootChildren, &$counts): void {
            AccreditationGroup::query()->update(['is_active' => false]);
            WorkingGroup::query()->update(['is_active' => false]);
            Standard::query()->update(['is_active' => false]);
            AssessmentElement::query()->update(['is_active' => false]);

            foreach (['MANAJEMEN', 'MEDIS'] as $groupIndex => $groupCode) {
                $groupFolderId = $this->requireUniqueFolder($groupCode, $rootChildren, 'folder induk');
                $group = AccreditationGroup::query()->where('drive_folder_id', $groupFolderId)->first()
                    ?? AccreditationGroup::query()->where('code', $groupCode)->first()
                    ?? new AccreditationGroup;
                $group->fill([
                    'code' => $groupCode,
                    'name' => $groupCode,
                    'sort_order' => $groupIndex + 1,
                    'drive_folder_id' => $groupFolderId,
                    'is_active' => true,
                ])->save();
                $counts['groups']++;

                $workingGroupFolders = $childrenByParent[$groupFolderId] ?? [];
                usort($workingGroupFolders, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

                foreach ($workingGroupFolders as $workingGroupIndex => $workingGroupFolder) {
                    $workingGroupCode = mb_strtoupper(trim($workingGroupFolder['name']));
                    $workingGroup = WorkingGroup::query()->where('drive_folder_id', $workingGroupFolder['id'])->first()
                        ?? WorkingGroup::query()->where('code', $workingGroupCode)->first()
                        ?? new WorkingGroup;
                    $workingGroup->fill([
                        'accreditation_group_id' => $group->id,
                        'code' => $workingGroupCode,
                        'name' => $workingGroupFolder['name'],
                        'sort_order' => $workingGroupIndex + 1,
                        'drive_folder_id' => $workingGroupFolder['id'],
                        'is_active' => true,
                    ])->save();
                    $counts['working_groups']++;

                    $standardFolders = $childrenByParent[$workingGroupFolder['id']] ?? [];
                    usort($standardFolders, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

                    foreach ($standardFolders as $standardIndex => $standardFolder) {
                        $standardCode = trim($standardFolder['name']);
                        $standard = Standard::query()->where('drive_folder_id', $standardFolder['id'])->first()
                            ?? Standard::query()->where('code', $standardCode)->first()
                            ?? new Standard;
                        $standard->fill([
                            'working_group_id' => $workingGroup->id,
                            'code' => $standardCode,
                            'title' => $standardFolder['name'],
                            'sort_order' => $standardIndex + 1,
                            'drive_folder_id' => $standardFolder['id'],
                            'is_active' => true,
                        ])->save();
                        $counts['standards']++;

                        $elementFolders = $childrenByParent[$standardFolder['id']] ?? [];
                        usort($elementFolders, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

                        foreach ($elementFolders as $elementIndex => $elementFolder) {
                            $elementCode = $standardCode.' / '.trim($elementFolder['name']);
                            $element = AssessmentElement::query()->where('drive_folder_id', $elementFolder['id'])->first()
                                ?? AssessmentElement::query()->where('code', $elementCode)->first()
                                ?? new AssessmentElement;
                            $requirements = config('accreditation.document_requirements', []);
                            $requirement = is_array($requirements) ? ($requirements[$elementCode] ?? null) : null;
                            $element->fill([
                                'standard_id' => $standard->id,
                                'code' => $elementCode,
                                'description' => $elementFolder['name'],
                                'sort_order' => $elementIndex + 1,
                                'drive_folder_id' => $elementFolder['id'],
                                'is_active' => true,
                            ]);

                            if (is_array($requirement)) {
                                if ($element->required_document_count === null) {
                                    $element->required_document_count = $requirement['count'] ?? null;
                                }

                                if ($element->evidence_notes === null) {
                                    $element->evidence_notes = $requirement['evidence'] ?? null;
                                }
                            }

                            $element->save();
                            $counts['assessment_elements']++;
                        }
                    }
                }
            }
        });

        GoogleDriveSetting::current()->update(['structure_synced_at' => now()]);

        return ['root' => (string) config('services.google_drive.root_folder_name')] + $counts;
    }

    /** @return array{root: string, groups: int, working_groups: int, standards: int, assessment_elements: int} */
    public function syncExistingStructure(): array
    {
        return $this->syncDatabaseStructureFromDrive();
    }

    public function ensureAssessmentElementFolder(AssessmentElement $element): string
    {
        if (! $element->drive_folder_id) {
            $this->syncDatabaseStructureFromDrive();
            $element->refresh();
        }

        if (! $element->drive_folder_id) {
            throw new RuntimeException('Folder EP tidak ditemukan pada struktur Google Drive yang tersinkronisasi.');
        }

        return $element->drive_folder_id;
    }

    /** @return array{id: string, name: string, mimeType: string, size?: string, webViewLink?: string} */
    public function upload(string $path, string $originalName, string $mimeType, AssessmentElement $element): array
    {
        $folderId = $this->ensureAssessmentElementFolder($element);
        $stream = fopen($path, 'r');

        if ($stream === false) {
            throw new RuntimeException('File sementara tidak dapat dibaca.');
        }

        try {
            return $this->request()
                ->withQueryParameters([
                    'uploadType' => 'multipart',
                    'fields' => 'id,name,mimeType,size,webViewLink',
                ])
                ->attach('metadata', json_encode([
                    'name' => $originalName,
                    'parents' => [$folderId],
                ], JSON_THROW_ON_ERROR), 'metadata.json', ['Content-Type' => 'application/json; charset=UTF-8'])
                ->attach('file', $stream, $originalName, ['Content-Type' => $mimeType])
                ->post(self::UPLOAD_URL.'/files')
                ->throw()
                ->json();
        } finally {
            fclose($stream);
        }
    }

    public function download(string $fileId, string $fileName, string $mimeType): StreamedResponse
    {
        $token = $this->accessToken();

        return response()->streamDownload(function () use ($fileId, $token): void {
            $response = $this->http()->withToken($token)->withOptions(['stream' => true])
                ->get(self::API_URL.'/files/'.$fileId, ['alt' => 'media'])
                ->throw();
            $body = $response->toPsrResponse()->getBody();

            while (! $body->eof()) {
                echo $body->read(8192);
            }
        }, $fileName, ['Content-Type' => $mimeType]);
    }

    public function preview(string $fileId, string $fileName, string $mimeType): StreamedResponse
    {
        $token = $this->accessToken();

        return response()->stream(function () use ($fileId, $token): void {
            $response = $this->http()->withToken($token)->withOptions(['stream' => true])
                ->get(self::API_URL.'/files/'.$fileId, ['alt' => 'media'])
                ->throw();
            $body = $response->toPsrResponse()->getBody();

            while (! $body->eof()) {
                echo $body->read(8192);
            }
        }, 200, [
            'Content-Type' => $mimeType,
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $fileName),
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function delete(string $fileId): void
    {
        $response = $this->request()->delete(self::API_URL.'/files/'.$fileId);

        if (! $response->successful() && $response->status() !== 404) {
            $response->throw();
        }
    }

    /** @return array<string, mixed> */
    public function testConnection(): array
    {
        return $this->request()->get(self::API_URL.'/about', ['fields' => 'user,storageQuota'])->throw()->json();
    }

    private function findRootFolder(): string
    {
        $name = (string) config('services.google_drive.root_folder_name');
        $escapedName = $this->escapeQueryValue($name);
        $folders = $this->listFolders("name = '{$escapedName}' and mimeType = 'application/vnd.google-apps.folder' and trashed = false");

        if ($folders === []) {
            throw new RuntimeException("Folder induk '{$name}' tidak ditemukan di Google Drive akun ini.");
        }

        if (count($folders) > 1) {
            throw new RuntimeException("Ditemukan lebih dari satu folder bernama '{$name}'. Isi Folder ID secara manual agar tidak ambigu.");
        }

        return $folders[0]['id'];
    }

    /** @return array<int, array{id: string, name: string}> */
    private function listChildFolders(string $parentId): array
    {
        $escapedParentId = $this->escapeQueryValue($parentId);

        return $this->listFolders("'{$escapedParentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false");
    }

    /** @return array<int, array{id: string, name: string}> */
    private function listFolders(string $query): array
    {
        $folders = [];
        $pageToken = null;

        do {
            $parameters = [
                'q' => $query,
                'spaces' => 'drive',
                'pageSize' => 1000,
                'orderBy' => 'name_natural',
                'fields' => 'nextPageToken,files(id,name)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ];

            if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
            }

            $response = $this->request()->get(self::API_URL.'/files', $parameters)->throw()->json();

            foreach ($response['files'] ?? [] as $folder) {
                $folders[] = ['id' => (string) $folder['id'], 'name' => (string) $folder['name']];
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        return $folders;
    }

    /** @return array<int, array{id: string, name: string, parents: array<int, string>}> */
    private function listAllFolders(): array
    {
        $folders = [];
        $pageToken = null;

        do {
            $parameters = [
                'q' => "mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'spaces' => 'drive',
                'pageSize' => 1000,
                'fields' => 'nextPageToken,files(id,name,parents)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ];

            if ($pageToken) {
                $parameters['pageToken'] = $pageToken;
            }

            $response = $this->request()->get(self::API_URL.'/files', $parameters)->throw()->json();

            foreach ($response['files'] ?? [] as $folder) {
                $folders[] = [
                    'id' => (string) $folder['id'],
                    'name' => (string) $folder['name'],
                    'parents' => array_values(array_map('strval', $folder['parents'] ?? [])),
                ];
            }

            $pageToken = $response['nextPageToken'] ?? null;
        } while ($pageToken);

        return $folders;
    }

    private function createFolder(string $name, ?string $parentId = null): string
    {
        $payload = ['name' => $name, 'mimeType' => 'application/vnd.google-apps.folder'];

        if ($parentId) {
            $payload['parents'] = [$parentId];
        }

        return $this->request()->post(self::API_URL.'/files', $payload)->throw()->json('id');
    }

    /** @param array<int, array{id: string, name: string}> $children */
    private function findOrCreateFromChildren(
        string $name,
        string $parentId,
        array &$children,
        int &$created,
        int &$reused,
    ): string {
        $folders = array_values(array_filter(
            $children,
            fn (array $folder): bool => $folder['name'] === $name,
        ));

        if (count($folders) > 1) {
            throw new RuntimeException("Ditemukan lebih dari satu folder '{$name}' pada induk yang sama.");
        }

        if ($folders !== []) {
            $reused++;

            return $folders[0]['id'];
        }

        $created++;
        $folderId = $this->createFolder($name, $parentId);
        $children[] = ['id' => $folderId, 'name' => $name];

        return $folderId;
    }

    /** @param array<int, array{id: string, name: string}> $folders */
    private function findUniqueFolderId(string $name, array $folders): ?string
    {
        $matches = array_values(array_filter($folders, fn (array $folder): bool => $folder['name'] === $name));

        if (count($matches) > 1) {
            throw new RuntimeException("Ditemukan lebih dari satu folder '{$name}' pada induk yang sama.");
        }

        return $matches[0]['id'] ?? null;
    }

    /** @param array<int, array{id: string, name: string}> $folders */
    private function requireUniqueFolder(string $name, array $folders, string $parentName): string
    {
        return $this->findUniqueFolderId($name, $folders)
            ?? throw new RuntimeException("Folder '{$name}' tidak ditemukan langsung di bawah {$parentName}.");
    }

    private function moveFolder(string $folderId, string $oldParentId, string $newParentId): void
    {
        $this->request()->withQueryParameters([
            'addParents' => $newParentId,
            'removeParents' => $oldParentId,
            'supportsAllDrives' => 'true',
            'fields' => 'id,parents',
        ])->patch(self::API_URL.'/files/'.$folderId)->throw();
    }

    private function request(): PendingRequest
    {
        return $this->http()->withToken($this->accessToken())->acceptJson();
    }

    private function http(): PendingRequest
    {
        $request = Http::connectTimeout(10)->timeout(60);
        $proxy = config('services.google_drive.proxy');

        if (is_string($proxy) && filled($proxy)) {
            $request->withOptions(['proxy' => $proxy]);
        }

        return $request;
    }

    private function accessToken(): string
    {
        $setting = GoogleDriveSetting::current();
        $this->ensureConnected($setting);

        if ($setting->token_expires_at?->isFuture()) {
            return $setting->access_token;
        }

        try {
            $response = $this->http()->asForm()->post('https://oauth2.googleapis.com/token', [
                'client_id' => config('services.google_drive.client_id'),
                'client_secret' => config('services.google_drive.client_secret'),
                'refresh_token' => $setting->refresh_token,
                'grant_type' => 'refresh_token',
            ])->throw();
        } catch (RequestException $exception) {
            if ($exception->response->status() === 400 && $exception->response->json('error') === 'invalid_grant') {
                $setting->update([
                    'access_token' => null,
                    'token_expires_at' => null,
                    'reauthorization_required_at' => now(),
                ]);

                throw new GoogleDriveReauthorizationRequiredException;
            }

            throw $exception;
        }

        $setting->update([
            'access_token' => $response->json('access_token'),
            'token_expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600) - 60),
        ]);

        return $setting->access_token;
    }

    private function ensureCredentialsConfigured(): void
    {
        if (! config('services.google_drive.client_id') || ! config('services.google_drive.client_secret')) {
            throw new RuntimeException('GOOGLE_DRIVE_CLIENT_ID dan GOOGLE_DRIVE_CLIENT_SECRET belum dikonfigurasi.');
        }
    }

    private function ensureConnected(GoogleDriveSetting $setting): void
    {
        $this->ensureCredentialsConfigured();

        if (! $setting->refresh_token) {
            throw new RuntimeException('Google Drive belum terhubung oleh admin.');
        }
    }

    private function escapeQueryValue(string $value): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $value);
    }
}
