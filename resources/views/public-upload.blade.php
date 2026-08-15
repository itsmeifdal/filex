<!DOCTYPE html>
<html lang="id">
    <head>
        @include('partials.head', ['title' => 'Repository Dokumen Akreditasi'])
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-emerald-900/10 bg-emerald-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-400 font-bold text-emerald-950">AR</span>
                    <span>
                        <span class="block text-sm font-semibold sm:text-base">Repository Akreditasi RS</span>
                        <span class="block text-xs text-emerald-200">Temukan EP dan unggah dokumen tanpa akun</span>
                    </span>
                </a>
                <a href="{{ url('/admin') }}" class="rounded-lg border border-white/20 px-3 py-2 text-sm font-medium hover:bg-white/10">Panel petugas</a>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-4 py-7 sm:px-6 lg:py-10">
            <livewire:public-document-upload />
        </main>

        <footer class="border-t border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
            Repository Akreditasi RS · Dokumen disimpan secara privat di Google Drive.
        </footer>
        @fluxScripts
    </body>
</html>
