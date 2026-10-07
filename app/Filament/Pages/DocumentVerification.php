<?php

namespace App\Filament\Pages;

use App\Filament\Exports\DocumentVerificationExporter;
use App\Models\AccreditationDocument;
use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

class DocumentVerification extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Verifikasi Dokumen';

    protected static ?string $title = 'Verifikasi Dokumen';

    protected static ?string $slug = 'verifikasi-dokumen';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.document-verification';

    /** @var array<int, int> */
    public array $expandedWorkingGroups = [];

    /** @var array<int, int> */
    public array $expandedStandards = [];

    public ?int $selectedAssessmentElementId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user?->is_active && in_array($user->role, ['admin', 'surveyor'], true);
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make('exportDocumentVerification')
                ->label('Export daftar verifikasi')
                ->exporter(DocumentVerificationExporter::class)
                ->formats([ExportFormat::Xlsx])
                ->columnMapping(false),
        ];
    }

    public function downloadWorkingGroupArchiveAction(): Action
    {
        return Action::make('downloadWorkingGroupArchive')
            ->label('Unduh folder')
            ->hiddenLabel()
            ->tooltip('Unduh folder POKJA')
            ->color('success')
            ->extraAttributes(['class' => '!bg-emerald-700 hover:!bg-emerald-800 !text-white [&>.fi-icon]:!text-white'])
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->url(fn (array $arguments): string => route('working-groups.archive.download', $arguments['workingGroupId']));
    }

    public function toggleWorkingGroup(int $workingGroupId): void
    {
        abort_unless(WorkingGroup::query()->where('is_active', true)->whereKey($workingGroupId)->exists(), 404);

        if (in_array($workingGroupId, $this->expandedWorkingGroups, true)) {
            $this->expandedWorkingGroups = array_values(array_diff($this->expandedWorkingGroups, [$workingGroupId]));
            $standardIds = Standard::query()->where('working_group_id', $workingGroupId)->pluck('id')->all();
            $this->expandedStandards = array_values(array_diff($this->expandedStandards, $standardIds));

            return;
        }

        $this->expandedWorkingGroups[] = $workingGroupId;
    }

    public function toggleStandard(int $standardId): void
    {
        $standard = Standard::query()->where('is_active', true)->findOrFail($standardId);

        if (in_array($standardId, $this->expandedStandards, true)) {
            $this->expandedStandards = array_values(array_diff($this->expandedStandards, [$standardId]));

            return;
        }

        if (! in_array($standard->working_group_id, $this->expandedWorkingGroups, true)) {
            $this->expandedWorkingGroups[] = $standard->working_group_id;
        }

        $this->expandedStandards[] = $standardId;
    }

    public function verifyDocument(int $documentId): void
    {
        $this->updateDocumentStatus($documentId, 'verified', 'Dokumen ditandai terverifikasi.');
    }

    public function cancelDocumentVerification(int $documentId): void
    {
        $this->updateDocumentStatus($documentId, 'pending', 'Verifikasi dokumen dibatalkan.');
    }

    public function markDocumentNeedsRevision(int $documentId): void
    {
        $this->updateDocumentStatus($documentId, 'rejected', 'Dokumen ditandai perlu perbaikan.');
    }

    public function selectAssessmentElement(int $assessmentElementId): void
    {
        $element = AssessmentElement::query()
            ->with('standard')
            ->where('is_active', true)
            ->findOrFail($assessmentElementId);

        $this->selectedAssessmentElementId = $element->id;

        if (! in_array($element->standard->working_group_id, $this->expandedWorkingGroups, true)) {
            $this->expandedWorkingGroups[] = $element->standard->working_group_id;
        }

        if (! in_array($element->standard_id, $this->expandedStandards, true)) {
            $this->expandedStandards[] = $element->standard_id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $expandedWorkingGroups = array_values(array_unique(array_map('intval', $this->expandedWorkingGroups)));
        $expandedStandards = array_values(array_unique(array_map('intval', $this->expandedStandards)));

        $groups = AccreditationGroup::query()
            ->where('is_active', true)
            ->with(['workingGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->withCount(['standards' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $workingGroupIds = $groups->flatMap->workingGroups->pluck('id');
        $workingGroupVerificationCounts = $this->documentCounts($workingGroupIds, true);

        /** @var Collection<int, Collection<int, Standard>> $standardsByWorkingGroup */
        $standardsByWorkingGroup = $expandedWorkingGroups === []
            ? collect()
            : Standard::query()
                ->whereIn('working_group_id', $expandedWorkingGroups)
                ->where('is_active', true)
                ->withCount(['assessmentElements' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')
                ->get()
                ->groupBy('working_group_id');

        $visibleStandardIds = $standardsByWorkingGroup->flatten(1)->pluck('id');
        $standardVerificationCounts = $this->documentCounts($visibleStandardIds, false);

        /** @var Collection<int, Collection<int, AssessmentElement>> $elementsByStandard */
        $elementsByStandard = $expandedStandards === []
            ? collect()
            : AssessmentElement::query()
                ->whereIn('standard_id', $expandedStandards)
                ->where('is_active', true)
                ->with(['documents' => fn ($query) => $query->latest()])
                ->orderBy('sort_order')
                ->get()
                ->groupBy('standard_id');

        $totals = $this->sumCounts($workingGroupVerificationCounts);
        $selectedElement = $this->selectedAssessmentElementId === null
            ? null
            : AssessmentElement::query()
                ->where('is_active', true)
                ->with(['documents' => fn ($query) => $query->latest(), 'standard.workingGroup'])
                ->find($this->selectedAssessmentElementId);

        return compact(
            'groups',
            'standardsByWorkingGroup',
            'elementsByStandard',
            'workingGroupVerificationCounts',
            'standardVerificationCounts',
            'totals',
            'selectedElement',
        );
    }

    /**
     * @param  Collection<int, int> $ids
     * @return Collection<int, array{total: int, pending: int, verified: int, rejected: int}>
     */
    private function documentCounts(Collection $ids, bool $byWorkingGroup): Collection
    {
        if ($ids->isEmpty()) {
            return collect();
        }

        $query = AccreditationDocument::query()
            ->join('assessment_elements', 'assessment_elements.id', '=', 'accreditation_documents.assessment_element_id')
            ->join('standards', 'standards.id', '=', 'assessment_elements.standard_id')
            ->where('assessment_elements.is_active', true)
            ->where('standards.is_active', true);

        if ($byWorkingGroup) {
            $query
                ->whereIn('standards.working_group_id', $ids)
                ->selectRaw('standards.working_group_id as progress_key, accreditation_documents.status, COUNT(*) as aggregate')
                ->groupBy('standards.working_group_id', 'accreditation_documents.status');
        } else {
            $query
                ->whereIn('assessment_elements.standard_id', $ids)
                ->selectRaw('assessment_elements.standard_id as progress_key, accreditation_documents.status, COUNT(*) as aggregate')
                ->groupBy('assessment_elements.standard_id', 'accreditation_documents.status');
        }

        return $query
            ->get()
            ->groupBy('progress_key')
            ->map(function (Collection $rows): array {
                $byStatus = $rows->pluck('aggregate', 'status');

                return [
                    'total' => (int) $rows->sum('aggregate'),
                    'pending' => (int) $byStatus->get('pending', 0),
                    'verified' => (int) $byStatus->get('verified', 0),
                    'rejected' => (int) $byStatus->get('rejected', 0),
                ];
            });
    }

    /**
     * @param  Collection<int, array{total: int, pending: int, verified: int, rejected: int}> $counts
     * @return array{total: int, pending: int, verified: int, rejected: int}
     */
    private function sumCounts(Collection $counts): array
    {
        return [
            'total' => (int) $counts->sum('total'),
            'pending' => (int) $counts->sum('pending'),
            'verified' => (int) $counts->sum('verified'),
            'rejected' => (int) $counts->sum('rejected'),
        ];
    }

    private function updateDocumentStatus(int $documentId, string $status, string $message): void
    {
        $document = AccreditationDocument::query()->findOrFail($documentId);
        abort_unless(auth()->user()?->can('update', $document), 403);

        $document->update(['status' => $status]);

        Notification::make()->title($message)->success()->send();
    }
}
