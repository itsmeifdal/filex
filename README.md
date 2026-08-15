# Repository Akreditasi RS

MVP repository dokumen akreditasi rumah sakit berbasis Laravel 13, Filament 5, Livewire 4, MySQL, dan Google Drive API.

## Fitur

- Halaman publik tanpa akun berbentuk tree: semua Pokja langsung terlihat, Standar dan EP dapat di-expand/fold, lalu form unggah terbuka pada EP yang dipilih.
- Penyimpanan file privat di Google Drive; database hanya menyimpan struktur, metadata, status, dan ID Drive.
- Panel `/admin` untuk mengelola struktur, memeriksa dokumen, pengguna, dan integrasi Google Drive.
- Role `admin` memiliki akses penuh; `surveyor` hanya dapat membaca dan mengunduh dokumen.
- Seed awal tepat 2 kelompok, 16 Pokja, 228 Standar, dan 807 EP.
- Validasi relasi struktur, pembatasan unggahan per IP, honeypot, dan batas file 20 MB.

> Nama Pokja, Standar, dan EP pada seed sengaja berupa placeholder netral karena nomenklatur resmi belum disertakan. Ganti melalui panel admin sesuai pedoman yang berlaku.

## Instalasi

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm run build
php artisan serve
```

Atur koneksi MySQL dan akun admin pada `.env` sebelum menjalankan seed:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=akreditasi_rs
DB_USERNAME=root
DB_PASSWORD=

ADMIN_NAME=Administrator
ADMIN_EMAIL=admin@example.com
ADMIN_PASSWORD=ganti-dengan-password-kuat
```

Pada environment `local`, seeder memakai fallback `admin@akreditasi.test` / `password` hanya jika variabel admin tidak diisi. Jangan gunakan fallback tersebut di server.

## Google Drive

1. Buat OAuth Client bertipe **Web application** pada Google Cloud Console.
2. Aktifkan Google Drive API.
3. Isi kredensial:

```dotenv
GOOGLE_DRIVE_CLIENT_ID=...
GOOGLE_DRIVE_CLIENT_SECRET=...
GOOGLE_DRIVE_ROOT_FOLDER_NAME="Repository Dokumen Akreditasi"
```

4. Buka `/admin/google-drive-integration` dan salin URI yang ditampilkan ke **Authorized redirect URIs** pada OAuth Client.
5. Klik **Hubungkan Google Drive**, berikan izin, lalu jalankan sinkronisasi folder. Aplikasi mencari folder induk sesuai `GOOGLE_DRIVE_ROOT_FOLDER_NAME`, kemudian memasangkan folder `MANAJEMEN`, `MEDIS`, dan delapan Pokja di bawah masing-masing kelompok. Nama dan kode Pokja mengikuti nama folder Drive secara persis.

Aplikasi meminta scope `drive` karena harus membaca dan menulis di dalam struktur folder yang sudah ada. Struktur dokumen di Drive mengikuti hierarki `Repository Dokumen Akreditasi/KELOMPOK/POKJA/STANDAR/EP`. Tree publik mencerminkan struktur Drive saat halaman dibuka dan menyegarkannya berkala. Operasi aplikasi tetap dibatasi secara logis ke folder induk yang telah disinkronkan. Refresh token dan access token disimpan terenkripsi menggunakan `APP_KEY`.

Jika server harus memakai proxy untuk mencapai Google API, isi `GOOGLE_DRIVE_PROXY`, misalnya `http://127.0.0.1:7890`, lalu jalankan `php artisan config:clear`.

## Operasional

- Format unggahan: PDF, Word, Excel, PowerPoint, JPG, PNG.
- Maksimum file aplikasi: 20 MB. Pastikan `upload_max_filesize` dan `post_max_size` PHP minimal 20 MB.
- Backup database dan Google Drive tetap diperlukan.
- Jalankan queue worker hanya jika nanti `QUEUE_CONNECTION` diubah untuk proses tambahan; MVP mengunggah secara sinkron agar sederhana.

## Verifikasi

```bash
composer lint:check
composer types:check
php artisan test
npm run build
```
