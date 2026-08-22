<x-filament-panels::page>
    @if (session('drive_success'))
        <div class="rounded-xl bg-success-50 p-4 text-sm text-success-700 dark:bg-success-950 dark:text-success-300">{{ session('drive_success') }}</div>
    @endif
    @if (session('drive_error'))
        <div class="rounded-xl bg-danger-50 p-4 text-sm text-danger-700 dark:bg-danger-950 dark:text-danger-300">{{ session('drive_error') }}</div>
    @endif

    <x-filament::section>
        <x-slot name="heading">Status koneksi</x-slot>
        <x-slot name="description">Aplikasi memakai OAuth 2.0 untuk membaca struktur lama dan mengunggah dokumen hanya di bawah folder induk akreditasi.</x-slot>

        <div class="space-y-4">
            @if ($this->setting->reauthorization_required_at)
                <div class="rounded-xl border border-warning-200 bg-warning-50 p-4 text-sm text-warning-800 dark:border-warning-800 dark:bg-warning-950 dark:text-warning-200">
                    <p class="font-semibold">Koneksi Google Drive perlu dihubungkan ulang.</p>
                    <p class="mt-1">Token akses untuk akun {{ $this->setting->connected_email ?: 'Google Drive' }} telah kedaluwarsa atau dicabut. Data sinkronisasi terakhir tetap tersimpan.</p>
                </div>
                <x-filament::button tag="a" href="{{ route('google-drive.connect') }}">Hubungkan ulang Google Drive</x-filament::button>
            @elseif ($this->setting->refresh_token)
                <p class="text-sm">Terhubung sebagai <strong>{{ $this->setting->connected_email ?: 'akun Google' }}</strong>.</p>
                <div class="flex flex-wrap gap-3">
                    <x-filament::button wire:click="testConnection" color="gray">Uji koneksi</x-filament::button>
                    <x-filament::button wire:click="syncStructure" wire:loading.attr="disabled" wire:target="syncStructure">
                        <span wire:loading.remove wire:target="syncStructure">Sinkronkan sekarang</span>
                        <span wire:loading wire:target="syncStructure">Menyinkronkan struktur…</span>
                    </x-filament::button>
                    <form method="POST" action="{{ route('google-drive.disconnect') }}" onsubmit="return confirm('Putuskan Google Drive?')">
                        @csrf
                        <x-filament::button type="submit" color="danger">Putuskan</x-filament::button>
                    </form>
                </div>
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    @if ($this->setting->structure_synced_at)
                        Sinkronisasi struktur terakhir: <strong>{{ $this->setting->structure_synced_at->timezone(config('app.timezone'))->translatedFormat('d F Y, H:i') }}</strong>.
                    @else
                        Struktur folder belum pernah disinkronkan secara berhasil.
                    @endif
                </p>
            @else
                <p class="text-sm text-gray-600 dark:text-gray-300">Belum terhubung. Pastikan Client ID dan Client Secret telah diisi di file environment.</p>
                <x-filament::button tag="a" href="{{ route('google-drive.connect') }}">Hubungkan Google Drive</x-filament::button>
            @endif
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Folder induk</x-slot>
        <x-slot name="description">Aplikasi akan mencari folder “{{ config('services.google_drive.root_folder_name') }}”. Jika ada nama ganda, isi ID folder yang benar secara manual.</x-slot>
        <form wire:submit="saveRootFolder" class="space-y-4">
            <div>
                <label class="mb-1 block text-sm font-medium">Google Drive Folder ID</label>
                <input wire:model="rootFolderId" type="text" class="fi-input block w-full rounded-lg border-gray-300 bg-white px-3 py-2 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Contoh: 1AbCdEf...">
                @error('rootFolderId') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
            </div>
            <div class="flex flex-wrap gap-3">
                <x-filament::button type="submit">Simpan ID folder</x-filament::button>
                @if ($this->setting->refresh_token && ! $this->setting->reauthorization_required_at)
                    <x-filament::button type="button" wire:click="syncStructure" wire:loading.attr="disabled" wire:target="syncStructure" color="gray">Cari dan sinkronkan sekarang</x-filament::button>
                @endif
            </div>
        </form>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Struktur yang diharapkan</x-slot>
        <div class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
            <p><strong>{{ config('services.google_drive.root_folder_name') }}</strong></p>
            <p class="pl-5">↳ MANAJEMEN — tepat 8 folder Pokja</p>
            <p class="pl-5">↳ MEDIS — tepat 8 folder Pokja</p>
            <p class="pt-2">Nama dan kode 16 Pokja di database akan diperbarui sama persis dengan nama folder Pokja di Google Drive.</p>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Redirect URI Google Cloud</x-slot>
        <p class="text-sm text-gray-600 dark:text-gray-300">Tambahkan URI berikut persis ke <em>Authorized redirect URIs</em> pada OAuth Client:</p>
        <code class="mt-2 block overflow-x-auto rounded-lg bg-gray-950 p-3 text-sm text-gray-100">{{ route('google-drive.callback') }}</code>
    </x-filament::section>
</x-filament-panels::page>
