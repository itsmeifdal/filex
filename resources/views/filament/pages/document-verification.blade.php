<x-filament-panels::page>
    <div class="verification-public space-y-6">
        <x-filament::section>
            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <x-filament::section compact><p class="text-sm text-gray-500 dark:text-gray-400">Total file</p><p class="mt-1 text-3xl font-bold">{{ $totals['total'] }}</p></x-filament::section>
                <x-filament::section compact><p class="text-sm text-gray-500 dark:text-gray-400">Menunggu verifikasi</p><p class="mt-1 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $totals['pending'] }}</p></x-filament::section>
                <x-filament::section compact><p class="text-sm text-gray-500 dark:text-gray-400">Terverifikasi</p><p class="mt-1 text-3xl font-bold text-success-600 dark:text-success-400">{{ $totals['verified'] }}</p></x-filament::section>
                <x-filament::section compact><p class="text-sm text-gray-500 dark:text-gray-400">Perlu perbaikan</p><p class="mt-1 text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $totals['rejected'] }}</p></x-filament::section>
            </div>
        </x-filament::section>

        @foreach ($groups as $group)
            <x-filament::section class="verification-group">
                <x-slot name="heading">{{ $group->name }}</x-slot>
                <x-slot name="description">{{ $group->workingGroups->count() }} Pokja</x-slot>

                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($group->workingGroups as $workingGroup)
                        @php($workingGroupOpen = in_array($workingGroup->id, $this->expandedWorkingGroups, true))
                        @php($counts = $workingGroupVerificationCounts->get($workingGroup->id, ['total' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0]))
                        <div>
                            <div class="flex items-center gap-3 px-5 py-4 transition hover:bg-emerald-50 dark:hover:bg-emerald-400/5">
                                <button wire:click="toggleWorkingGroup({{ $workingGroup->id }})" type="button" aria-expanded="{{ $workingGroupOpen ? 'true' : 'false' }}" class="flex min-w-0 flex-1 items-center gap-3 text-left">
                                    <svg class="size-4 shrink-0 text-gray-400 transition-transform {{ $workingGroupOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m7 5 5 5-5 5V5Z"/></svg>
                                    <span class="min-w-0 flex-1">
                                        <span class="block font-semibold text-gray-900 dark:text-white">{{ $workingGroup->name }}</span>
                                        <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">{{ $workingGroup->standards_count }} Standar · {{ $counts['total'] }} file</span>
                                    </span>
                                </button>
                                <span class="hidden shrink-0 text-right text-xs sm:block">
                                    <span class="block font-bold text-amber-700 dark:text-amber-300">{{ $counts['pending'] }} menunggu</span>
                                    <span class="block text-emerald-700 dark:text-emerald-300">{{ $counts['verified'] }} terverifikasi</span>
                                </span>
                                {{ ($this->getAction('downloadWorkingGroupArchive', false))(['workingGroupId' => $workingGroup->id]) }}
                            </div>

                            @if ($workingGroupOpen)
                                <div class="border-t border-gray-100 bg-gray-50/70 py-1 pl-6 dark:border-white/10 dark:bg-white/[.03] sm:pl-10">
                                    @foreach ($standardsByWorkingGroup->get($workingGroup->id, collect()) as $standard)
                                        @php($standardOpen = in_array($standard->id, $this->expandedStandards, true))
                                        @php($standardCounts = $standardVerificationCounts->get($standard->id, ['total' => 0, 'pending' => 0, 'verified' => 0, 'rejected' => 0]))
                                        <div class="border-l border-gray-200 dark:border-white/10">
                                            <button wire:click="toggleStandard({{ $standard->id }})" type="button" aria-expanded="{{ $standardOpen ? 'true' : 'false' }}" class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-white dark:hover:bg-white/5">
                                                <svg class="mt-0.5 size-4 shrink-0 text-gray-400 transition-transform {{ $standardOpen ? 'rotate-90' : '' }}" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path d="m7 5 5 5-5 5V5Z"/></svg>
                                                <span class="min-w-0 flex-1"><span class="block text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">{{ $standard->code }}</span><span class="mt-0.5 block text-sm text-gray-700 dark:text-gray-200">{{ $standard->title }}</span></span>
                                                <span class="shrink-0 text-right text-xs"><span class="block text-gray-500 dark:text-gray-400">{{ $standard->assessment_elements_count }} EP</span><span class="mt-0.5 block font-semibold text-amber-700 dark:text-amber-300">{{ $standardCounts['pending'] }} menunggu</span></span>
                                            </button>

                                            @if ($standardOpen)
                                                <div class="ml-5 border-l border-dashed border-emerald-200 bg-white dark:border-emerald-400/30 dark:bg-gray-900 sm:ml-8">
                                                    @foreach ($elementsByStandard->get($standard->id, collect()) as $element)
                                                        @php($elementDocuments = $element->documents)
                                                        @php($elementPending = $elementDocuments->where('status', 'pending')->count())
                                                        @php($elementVerified = $elementDocuments->where('status', 'verified')->count())
                                                        <div class="border-t border-gray-100 px-4 py-4 dark:border-white/10">
                                                            <div class="flex items-start gap-3">
                                                                <span class="grid size-8 shrink-0 place-items-center rounded-lg bg-emerald-100 text-[10px] font-bold text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200">EP</span>
                                                                <div class="min-w-0 flex-1"><p class="text-xs font-bold text-gray-500 dark:text-gray-400">{{ $element->code }}</p><p class="mt-1 text-sm leading-5 text-gray-800 dark:text-gray-100">{{ $element->description }}</p><p class="mt-2 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $elementDocuments->count() }} file · {{ $elementVerified }} terverifikasi · {{ $elementPending }} menunggu</p></div>
                                                            </div>

                                                            @forelse ($elementDocuments as $document)
                                                                @php($documentStatus = match ($document->status) {
                                                                    'verified' => ['Terverifikasi', 'bg-emerald-100 text-emerald-800 dark:bg-emerald-400/15 dark:text-emerald-200'],
                                                                    'rejected' => ['Perlu perbaikan', 'bg-amber-100 text-amber-800 dark:bg-amber-400/15 dark:text-amber-200'],
                                                                    default => ['Menunggu verifikasi', 'bg-slate-100 text-slate-700 dark:bg-white/10 dark:text-gray-200'],
                                                                })
                                                                <div wire:key="verification-document-{{ $document->id }}" class="mt-3 flex flex-col gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/[.03] lg:flex-row lg:items-center">
                                                                    <div class="min-w-0 flex-1"><p class="truncate text-sm font-semibold text-gray-800 dark:text-gray-100" title="{{ $document->original_name }}">{{ $document->original_name }}</p><p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ number_format($document->size / 1024, 1) }} KB · {{ $document->created_at->format('d M Y H:i') }}</p><span class="mt-2 inline-flex rounded-md px-2 py-1 text-xs font-bold {{ $documentStatus[1] }}">{{ $documentStatus[0] }}</span></div>
                                                                    <div class="flex flex-wrap gap-2">
                                                                        <x-filament::button tag="a" href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" size="sm" color="gray">Preview</x-filament::button>
                                                                        @if ($document->status === 'verified')
                                                                            <x-filament::button wire:click="cancelDocumentVerification({{ $document->id }})" wire:confirm="Batalkan verifikasi file ini? Statusnya akan kembali ke menunggu verifikasi." size="sm" color="gray">Batal Verifikasi</x-filament::button>
                                                                        @else
                                                                            <x-filament::button wire:click="verifyDocument({{ $document->id }})" wire:confirm="Verifikasi file ini?" size="sm">Verifikasi</x-filament::button>
                                                                        @endif
                                                                        @if ($document->status !== 'rejected')
                                                                            <x-filament::button wire:click="markDocumentNeedsRevision({{ $document->id }})" wire:confirm="Tandai file ini perlu perbaikan?" size="sm" color="warning">Perlu perbaikan</x-filament::button>
                                                                        @endif
                                                                    </div>
                                                                </div>
                                                            @empty
                                                                <p class="mt-3 rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-500 dark:bg-white/5 dark:text-gray-400">Belum ada file yang diunggah ke EP ini.</p>
                                                            @endforelse
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
            </x-filament::section>
        @endforeach
    </div>
</x-filament-panels::page>
