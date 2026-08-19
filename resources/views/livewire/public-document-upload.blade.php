<div
    x-data
    wire:poll.60s="refreshStructure"
    x-on:element-selected.window="$nextTick(() => document.getElementById('upload-panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
>
    @if ($syncError)
        <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $syncError }}</div>
    @endif
    @if ($documentMessage)
        <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ $documentMessage }}</div>
    @endif
    @error('documentAction')
        <div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror
    <section class="mb-7 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
        <div class="max-w-2xl">
            <span class="inline-flex rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold tracking-wide text-emerald-800">REPOSITORY PUBLIK</span>
            <h1 class="mt-3 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Pilih EP, lalu unggah dokumennya.</h1>
            <p class="mt-3 leading-7 text-slate-600">Semua Pokja langsung terlihat. Buka Pokja untuk melihat Standar, lalu buka Standar untuk memilih Elemen Penilaian.</p>
        </div>
        <label class="relative block w-full lg:max-w-sm">
            <span class="sr-only">Cari Pokja</span>
            <svg class="pointer-events-none absolute left-3.5 top-3.5 size-5 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input wire:model.live.debounce.300ms="search" type="search" placeholder="Cari nama atau kode Pokja…" class="w-full rounded-xl border-slate-300 bg-white py-3 pl-11 pr-4 text-sm shadow-sm focus:border-emerald-600 focus:ring-emerald-600">
        </label>
    </section>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_390px]">
        <section aria-label="Struktur dokumen akreditasi" class="space-y-6">
            @foreach ($groups as $group)
                <article wire:key="group-{{ $group->id }}" class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <header class="flex items-center justify-between border-b border-slate-200 bg-slate-50 px-4 py-4 sm:px-5">
                        <div class="flex items-center gap-3">
                            <span class="grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M3 7.5h6l2 2H21v9.5a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7.5Z"/><path d="M3 7.5V5a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v2.5"/></svg>
                            </span>
                            <div>
                                <h2 class="font-bold text-slate-950">{{ $group->name }}</h2>
                                <p class="text-xs text-slate-500">{{ $group->workingGroups->count() }} Pokja</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-white px-2.5 py-1 text-xs font-semibold text-slate-500 ring-1 ring-slate-200">Kelompok</span>
                    </header>

                    <div class="divide-y divide-slate-100">
                        @forelse ($group->workingGroups as $workingGroup)
                            @php($workingGroupOpen = in_array($workingGroup->id, $expandedWorkingGroups, true))
                            @php($workingGroupHasTarget = $workingGroupRequiredDocuments->has($workingGroup->id))
                            @php($workingGroupRequired = (int) $workingGroupRequiredDocuments->get($workingGroup->id, 0))
                            @php($workingGroupUploaded = (int) $workingGroupUploadedDocuments->get($workingGroup->id, 0))
                            <div wire:key="working-group-{{ $workingGroup->id }}">
                                <button
                                    wire:click="toggleWorkingGroup({{ $workingGroup->id }})"
                                    type="button"
                                    aria-expanded="{{ $workingGroupOpen ? 'true' : 'false' }}"
                                    class="group flex w-full items-center gap-3 px-4 py-3.5 text-left hover:bg-emerald-50/60 sm:px-5"
                                >
                                    <svg class="size-4 shrink-0 text-slate-400 transition-transform {{ $workingGroupOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m7 5 5 5-5 5V5Z"/></svg>
                                    <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-amber-50 text-amber-600">
                                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 7h6l2 2h10v10a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V7Z"/></svg>
                                    </span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-sm font-semibold text-slate-800 group-hover:text-emerald-800">{{ $workingGroup->name }}</span>
                                        <span class="block text-xs text-slate-500">{{ $workingGroup->standards_count }} Standar</span>
                                    </span>
                                    @if ($workingGroupHasTarget)
                                        <span class="shrink-0 rounded-md px-2 py-1 text-xs font-bold {{ $workingGroupUploaded >= $workingGroupRequired ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">{{ $workingGroupUploaded }}/{{ $workingGroupRequired }} dokumen</span>
                                    @else
                                        <span class="hidden rounded-md bg-slate-100 px-2 py-1 text-xs font-medium text-slate-500 sm:inline">POKJA</span>
                                    @endif
                                </button>

                                @if ($workingGroupOpen)
                                    <div class="border-t border-slate-100 bg-slate-50/60 py-1 pl-5 sm:pl-10">
                                        @foreach ($standardsByWorkingGroup->get($workingGroup->id, collect()) as $standard)
                                            @php($standardOpen = in_array($standard->id, $expandedStandards, true))
                                            @php($standardHasTarget = $standardRequiredDocuments->has($standard->id))
                                            @php($standardRequired = (int) $standardRequiredDocuments->get($standard->id, 0))
                                            @php($standardUploaded = (int) $standardUploadedDocuments->get($standard->id, 0))
                                            <div wire:key="standard-{{ $standard->id }}" class="border-l border-slate-200">
                                                <button
                                                    wire:click="toggleStandard({{ $standard->id }})"
                                                    type="button"
                                                    aria-expanded="{{ $standardOpen ? 'true' : 'false' }}"
                                                    class="group flex w-full items-start gap-2.5 px-3 py-3 text-left hover:bg-white sm:px-4"
                                                >
                                                    <svg class="mt-0.5 size-4 shrink-0 text-slate-400 transition-transform {{ $standardOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m7 5 5 5-5 5V5Z"/></svg>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block text-xs font-bold uppercase tracking-wide text-emerald-700">{{ $standard->code }}</span>
                                                        <span class="mt-0.5 block text-sm text-slate-700">{{ $standard->title }}</span>
                                                    </span>
                                                    <span class="shrink-0 text-right text-xs">
                                                        <span class="block text-slate-400">{{ $standard->assessment_elements_count }} EP</span>
                                                        @if ($standardHasTarget)
                                                            <span class="mt-0.5 block font-semibold {{ $standardUploaded >= $standardRequired ? 'text-emerald-700' : 'text-amber-700' }}">{{ $standardUploaded }}/{{ $standardRequired }} dokumen</span>
                                                        @endif
                                                    </span>
                                                </button>

                                                @if ($standardOpen)
                                                    <div class="ml-5 border-l border-dashed border-emerald-200 bg-white sm:ml-8">
                                                        @foreach ($elementsByStandard->get($standard->id, collect()) as $element)
                                                            @php($selected = $assessmentElementId === $element->id)
                                                            @php($elementUploaded = $element->documents->count())
                                                            @php($elementScored = $element->required_document_count === null ? $elementUploaded : min($elementUploaded, max(0, $element->required_document_count)))
                                                            <div wire:key="element-{{ $element->id }}" class="border-t border-slate-100">
                                                                <button
                                                                    wire:click="selectElement({{ $element->id }})"
                                                                    type="button"
                                                                    class="flex w-full items-start gap-3 px-3 py-3 text-left transition {{ $selected ? 'bg-emerald-100 ring-1 ring-inset ring-emerald-300' : 'hover:bg-emerald-50' }} sm:px-4"
                                                                >
                                                                    <span class="mt-0.5 grid size-7 shrink-0 place-items-center rounded-md {{ $selected ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-700' }} text-[10px] font-bold">EP</span>
                                                                    <span class="min-w-0 flex-1">
                                                                        <span class="block text-xs font-semibold text-slate-500">{{ $element->code }}</span>
                                                                        <span class="mt-0.5 block text-sm leading-5 text-slate-700">{{ $element->description }}</span>
                                                                        @if ($element->required_document_count !== null)
                                                                            <span class="mt-1 block text-xs font-bold {{ $elementScored >= $element->required_document_count ? 'text-emerald-700' : 'text-amber-700' }}">{{ $elementScored }}/{{ $element->required_document_count }} dokumen</span>
                                                                        @endif
                                                                    </span>
                                                                    <span class="mt-1 shrink-0 rounded-lg px-2 py-1 text-xs font-semibold {{ $selected ? 'bg-emerald-700 text-white' : 'bg-white text-emerald-700 ring-1 ring-emerald-200' }}">{{ $selected ? 'Dipilih' : 'Unggah' }}</span>
                                                                </button>

                                                                @if ($element->documents->isNotEmpty())
                                                                    <div class="space-y-2 border-t border-dashed border-slate-200 bg-slate-50/70 px-3 py-3 sm:pl-14 sm:pr-4">
                                                                        @foreach ($element->documents as $document)
                                                                            <div wire:key="document-{{ $document->id }}" class="flex flex-col gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2.5 sm:flex-row sm:items-center">
                                                                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-sky-50 text-sky-700" aria-hidden="true">📄</span>
                                                                                <span class="min-w-0 flex-1">
                                                                                    <span class="block truncate text-sm font-semibold text-slate-700" title="{{ $document->original_name }}">{{ $document->original_name }}</span>
                                                                                    <span class="block text-xs text-slate-400">{{ number_format($document->size / 1024, 1) }} KB · {{ $document->created_at->format('d M Y H:i') }}</span>
                                                                                </span>
                                                                                <span class="flex shrink-0 items-center gap-2">
                                                                                    <a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" class="rounded-lg border border-sky-200 px-2.5 py-1.5 text-xs font-semibold text-sky-700 hover:bg-sky-50">Preview</a>
                                                                                    @if (in_array($document->id, $deletableDocumentIds, true))
                                                                                        <button wire:click="deleteDocument({{ $document->id }})" wire:confirm="Hapus file ini dari Google Drive?" type="button" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50">Hapus</button>
                                                                                    @endif
                                                                                </span>
                                                                            </div>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @empty
                            <p class="px-5 py-8 text-center text-sm text-slate-500">Pokja tidak ditemukan.</p>
                        @endforelse
                    </div>
                </article>
            @endforeach
        </section>

        <aside id="upload-panel" class="scroll-mt-4 xl:sticky xl:top-5 xl:self-start">
            @if ($selectedElement)
                <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-200 bg-emerald-950 px-5 py-5 text-white">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-emerald-300">Tujuan unggahan</p>
                                <h2 class="mt-1 font-bold">{{ $selectedElement->code }}</h2>
                            </div>
                            <button wire:click="clearSelection" type="button" class="rounded-lg p-1.5 text-emerald-200 hover:bg-white/10 hover:text-white" aria-label="Tutup panel unggah">✕</button>
                        </div>
                        <p class="mt-3 text-sm leading-5 text-emerald-100">{{ $selectedElement->description }}</p>
                        @if ($selectedElement->required_document_count !== null)
                            <p class="mt-3 rounded-lg bg-white/10 px-3 py-2 text-sm font-semibold text-white">{{ $selectedElement->documents()->count() }}/{{ $selectedElement->required_document_count }} dokumen tersedia</p>
                        @endif
                        <p class="mt-3 text-xs text-emerald-300">{{ $selectedElement->standard->workingGroup->name }} → {{ $selectedElement->standard->code }}</p>
                    </div>

                    @if (filled($selectedElement->evidence_notes))
                        <div class="border-b border-emerald-100 bg-emerald-50 px-5 py-4">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-800">Bukti yang dibutuhkan</p>
                            <p class="mt-1 text-sm leading-6 text-emerald-950">{{ $selectedElement->evidence_notes }}</p>
                        </div>
                    @endif

                    @if ($uploaded)
                        <div class="px-5 py-10 text-center">
                            <div class="mx-auto grid size-14 place-items-center rounded-full bg-emerald-100 text-2xl font-bold text-emerald-700">✓</div>
                            <h3 class="mt-4 text-lg font-bold text-slate-950">Dokumen berhasil dikirim</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600">File telah tersimpan dan menunggu verifikasi petugas.</p>
                            <button wire:click="uploadAnother" type="button" class="mt-5 rounded-xl bg-emerald-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-800">Unggah file lain ke EP ini</button>
                        </div>
                    @else
                        <form wire:submit="uploadDocument" class="space-y-5 p-5">
                            <div class="hidden" aria-hidden="true"><label>Website <input wire:model="website" type="text" tabindex="-1" autocomplete="off"></label></div>

                            <label class="block rounded-xl border-2 border-dashed border-slate-300 p-4 text-center hover:border-emerald-500">
                                <span class="block text-sm font-semibold text-slate-700">Pilih file dokumen *</span>
                                <span class="mt-1 block text-xs text-slate-500">PDF, Office, JPG, atau PNG · Maks. 20 MB</span>
                                <input wire:model="file" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png" class="mt-4 block w-full text-xs text-slate-600 file:mr-2 file:rounded-lg file:border-0 file:bg-emerald-100 file:px-3 file:py-2 file:font-semibold file:text-emerald-800">
                                <span wire:loading wire:target="file" class="mt-2 block text-xs text-emerald-700">Menyiapkan file…</span>
                                @error('file') <span class="mt-2 block text-xs text-red-600">{{ $message }}</span> @enderror
                            </label>

                            <button type="submit" wire:loading.attr="disabled" wire:target="uploadDocument,file" class="w-full rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white shadow-sm hover:bg-emerald-800 disabled:cursor-wait disabled:opacity-60">
                                <span wire:loading.remove wire:target="uploadDocument">Kirim dokumen ke EP ini</span>
                                <span wire:loading wire:target="uploadDocument">Mengunggah ke Google Drive…</span>
                            </button>
                        </form>
                    @endif
                </section>
            @else
                <section class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center shadow-sm xl:py-10">
                    <div class="mx-auto grid size-14 place-items-center rounded-2xl bg-emerald-100 text-emerald-700">
                        <svg class="size-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M4 4h16v16H4z"/><path d="M8 9h8M8 13h5"/><path d="m15 16 2 2 3-4"/></svg>
                    </div>
                    <h2 class="mt-4 text-lg font-bold text-slate-950">Belum ada EP dipilih</h2>
                    <p class="mt-2 text-sm leading-6 text-slate-600">Expand salah satu Pokja dan Standar, kemudian klik EP tujuan untuk menampilkan form unggah.</p>
                </section>
            @endif
        </aside>
    </div>
</div>
