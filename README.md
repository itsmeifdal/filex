# Repository Akreditasi RS

MVP repository dokumen akreditasi rumah sakit berbasis Laravel 13, Filament 5, Livewire 4, MySQL, dan Google Drive API.

## Fitur

- Halaman publik tanpa akun berbentuk tree: semua Pokja langsung terlihat, Standar dan EP dapat di-expand/fold, lalu form unggah terbuka pada EP yang dipilih.
- Penyimpanan file privat di Google Drive; database hanya menyimpan struktur, metadata, status, dan ID Drive.
- Panel `/admin` untuk mengelola struktur, memeriksa dokumen, pengguna, dan integrasi Google Drive.
- Role `admin` memiliki akses penuh; `surveyor` hanya dapat membaca dan mengunduh dokumen.
- Seed awal tepat 2 kelompok, 16 Pokja, 228 Standar, dan 807 EP.
- Validasi relasi struktur, pembatasan unggahan per IP, honeypot, dan batas file 20 MB.

## Operasional

- Format unggahan: PDF, Word, Excel, PowerPoint, JPG, PNG.
- Maksimum file aplikasi: 20 MB. Pastikan `upload_max_filesize` dan `post_max_size` PHP minimal 20 MB.
- Backup database dan Google Drive tetap diperlukan.
- Jalankan queue worker hanya jika nanti `QUEUE_CONNECTION` diubah untuk proses tambahan; MVP mengunggah secara sinkron agar sederhana.
```
