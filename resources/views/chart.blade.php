<!DOCTYPE html>
<html lang="id">
    <head>
        @include('partials.head', ['title' => 'Progress Upload Dokumen'])
        <style>
            .upload-chart {
                width: 100%;
            }

            .upload-chart-axis,
            .upload-chart-row {
                display: grid;
                grid-template-columns: 70px minmax(0, 1fr) 122px;
                column-gap: 10px;
            }

            .upload-chart-axis {
                align-items: end;
                margin-bottom: 4px;
            }

            .upload-chart-scale {
                display: flex;
                justify-content: space-between;
                color: #64748b;
                font-size: 0.64rem;
                font-variant-numeric: tabular-nums;
            }

            .upload-chart-row {
                align-items: center;
                border-radius: 0.5rem;
                min-height: 24px;
                padding: 1px 4px;
                transition: background-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
            }

            .upload-chart-rows > * + * {
                margin-top: 2px;
            }

            .upload-chart-row:hover,
            .upload-chart-row:focus {
                background: #ecfdf5;
                box-shadow: 0 3px 10px rgba(6, 78, 59, 0.1);
                outline: none;
                transform: translateX(2px);
            }

            .upload-chart-label {
                color: #064e3b;
                font-size: 0.72rem;
                font-weight: 700;
                line-height: 1.25;
            }

            .upload-chart-label span {
                display: none;
            }

            .upload-chart-plot {
                background-color: #f8fafc;
                background-image: linear-gradient(to right, rgba(100, 116, 139, 0.22) 1px, transparent 1px);
                background-size: 25% 100%;
                border-bottom: 1px solid #cbd5e1;
                border-left: 1px solid #cbd5e1;
                height: 20px;
                padding: 4px 0;
                position: relative;
            }

            .upload-chart-bar {
                border-radius: 0 5px 5px 0;
                box-shadow: 0 3px 8px rgba(15, 118, 110, 0.22);
                height: 12px;
                min-width: 0;
                transition: width 500ms ease;
            }

            .upload-chart-zero-marker {
                border-radius: 0 4px 4px 0;
                display: block;
                height: 12px;
                left: 0;
                position: absolute;
                top: 4px;
                width: 4px;
            }

            .upload-chart-value {
                color: #0f172a;
                font-size: 0.7rem;
                font-variant-numeric: tabular-nums;
                line-height: 1;
                text-align: right;
                white-space: nowrap;
            }

            .upload-chart-value strong {
                color: #047857;
                font-size: 0.76rem;
            }

            @media (max-width: 700px) {
                .upload-chart-scroll {
                    margin-inline: -1.25rem;
                    padding-inline: 1.25rem;
                }

                .upload-chart {
                    min-width: 590px;
                }
            }
        </style>
    </head>
    <body class="sea-fern min-h-screen bg-slate-50 text-slate-900 antialiased">
        <header class="border-b border-emerald-900/10 bg-emerald-950 text-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <a href="{{ route('home') }}" class="flex items-center gap-3">
                    <span class="grid size-10 place-items-center rounded-xl bg-emerald-400 font-bold text-emerald-950">AR</span>
                    <span>
                        <span class="block text-sm font-semibold sm:text-base">Repository Akreditasi RS</span>
                        <span class="block text-xs text-emerald-200">Progress upload dokumen tiap Pokja</span>
                    </span>
                </a>
                <a href="{{ route('home') }}" class="shrink-0 rounded-lg border border-emerald-200/30 px-3 py-2 text-sm font-semibold text-emerald-50 transition hover:bg-emerald-900">
                    Kembali ke unggah
                </a>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-5 sm:px-6 lg:py-6">
            <div class="mb-5">
                <p class="mb-1 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-emerald-700">Monitoring dokumen</p>
                <h1 class="sea-fern-heading text-2xl font-bold text-emerald-950 sm:text-3xl">Progress Upload per Pokja</h1>
                <p class="mt-1 max-w-3xl text-xs leading-5 text-slate-600 sm:text-sm">
                    Persentase mengikuti skor penilaian 0–10. Setiap batang menampilkan jumlah dokumen yang sudah terunggah dibandingkan targetnya.
                </p>
            </div>

            <section class="overflow-hidden rounded-2xl border border-emerald-900/10 bg-white shadow-sm">
                <div class="border-b border-slate-100 bg-emerald-50/70 px-5 py-3 sm:px-6">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="font-semibold text-emerald-950">Urutan capaian tertinggi</h2>
                        <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-emerald-800 shadow-sm">Skor 0–10 → 0–100%</span>
                    </div>
                </div>

                @if ($progressItems->isNotEmpty())
                    <div class="upload-chart-scroll overflow-x-auto px-5 py-3 sm:px-6 sm:py-4">
                        <div class="upload-chart">
                            <div class="upload-chart-axis">
                                <span></span>
                                <div class="upload-chart-scale" aria-hidden="true">
                                    <span>0%</span><span>25%</span><span>50%</span><span>75%</span><span>100%</span>
                                </div>
                                <span class="text-right text-[0.7rem] font-semibold text-slate-500">Progress dokumen</span>
                            </div>

                            <div class="upload-chart-rows">
                                @foreach ($progressItems as $item)
                                    @php
                                        $barColor = $item['group'] === 'MEDIS'
                                            ? 'linear-gradient(90deg, #0369a1, #38bdf8)'
                                            : 'linear-gradient(90deg, #047857, #2dd4bf)';
                                    @endphp
                                    <article class="upload-chart-row" tabindex="0">
                                        <p class="upload-chart-label">{{ $item['code'] }}</p>
                                        <div class="upload-chart-plot" role="progressbar" aria-label="Progress {{ $item['name'] }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $item['percentage'] }}" title="{{ $item['name'] }}: {{ $item['percentage'] }}% ({{ $item['uploaded'] }}/{{ $item['required'] }} dokumen)">
                                            <div class="upload-chart-bar" style="width: {{ $item['percentage'] }}%; background: {{ $barColor }}"></div>
                                            @if ($item['percentage'] === 0)
                                                <span class="upload-chart-zero-marker" style="background: {{ $barColor }}" aria-hidden="true"></span>
                                            @endif
                                        </div>
                                        <p class="upload-chart-value"><strong>{{ $item['percentage'] }}%</strong> ({{ $item['uploaded'] }}/{{ $item['required'] }} dokumen)</p>
                                    </article>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @else
                    <div class="py-14 text-center">
                        <p class="font-semibold text-emerald-950">Belum ada data Pokja aktif.</p>
                        <p class="mt-1 text-sm text-slate-500">Progress akan muncul setelah struktur akreditasi tersedia.</p>
                    </div>
                @endif
            </section>
        </main>

        <footer class="border-t border-slate-200 px-4 py-6 text-center text-xs text-slate-500">
            Repository Akreditasi RS · Dokumen disimpan secara privat di Google Drive.
        </footer>
    </body>
</html>
