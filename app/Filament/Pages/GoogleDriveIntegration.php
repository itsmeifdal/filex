<?php

namespace App\Filament\Pages;

use App\Exceptions\GoogleDriveReauthorizationRequiredException;
use App\Models\GoogleDriveSetting;
use App\Services\GoogleDriveService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

class GoogleDriveIntegration extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCloud;

    protected static ?string $navigationLabel = 'Google Drive';

    protected static ?string $title = 'Integrasi Google Drive';

    protected static ?int $navigationSort = 30;

    protected string $view = 'filament.pages.google-drive-integration';

    public ?string $rootFolderId = null;

    public function mount(): void
    {
        $this->rootFolderId = GoogleDriveSetting::current()->root_folder_id;
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    public function saveRootFolder(): void
    {
        $this->validate(['rootFolderId' => ['nullable', 'string', 'max:255']]);
        GoogleDriveSetting::current()->update(['root_folder_id' => filled($this->rootFolderId) ? trim($this->rootFolderId) : null]);
        Notification::make()->title('Folder induk disimpan')->success()->send();
    }

    public function syncStructure(GoogleDriveService $drive): void
    {
        try {
            $result = $drive->syncExistingStructure();
            $this->rootFolderId = GoogleDriveSetting::current()->root_folder_id;
            Notification::make()
                ->title('Struktur Google Drive tersinkronisasi')
                ->body("{$result['root']}: {$result['groups']} kelompok dan {$result['working_groups']} Pokja dipasangkan.")
                ->success()
                ->send();
        } catch (GoogleDriveReauthorizationRequiredException) {
            Notification::make()
                ->title('Hubungkan ulang Google Drive')
                ->body('Izin akses Google telah kedaluwarsa atau dicabut. Gunakan tombol Hubungkan ulang Google Drive.')
                ->warning()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Sinkronisasi gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function testConnection(GoogleDriveService $drive): void
    {
        try {
            $drive->testConnection();
            Notification::make()->title('Koneksi Google Drive aktif')->success()->send();
        } catch (GoogleDriveReauthorizationRequiredException) {
            Notification::make()
                ->title('Hubungkan ulang Google Drive')
                ->body('Izin akses Google telah kedaluwarsa atau dicabut. Gunakan tombol Hubungkan ulang Google Drive.')
                ->warning()
                ->send();
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()->title('Koneksi Google Drive gagal')->body($exception->getMessage())->danger()->send();
        }
    }

    public function getSettingProperty(): GoogleDriveSetting
    {
        return GoogleDriveSetting::current();
    }
}
