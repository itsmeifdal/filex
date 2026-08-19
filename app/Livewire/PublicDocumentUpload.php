<?php

namespace App\Livewire;

use App\Models\AccreditationDocument;
use App\Models\AccreditationGroup;
use App\Models\AssessmentElement;
use App\Models\Standard;
use App\Models\WorkingGroup;
use App\Services\GoogleDriveService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Features\SupportFileUploads\WithFileUploads;
use Throwable;

class PublicDocumentUpload extends Component
{
    use WithFileUploads;

    /** @var array<int, int> */
    public array $expandedWorkingGroups = [];

    /** @var array<int, int> */
    public array $expandedStandards = [];

    public string $search = '';

    public ?int $groupId = null;

    public ?int $workingGroupId = null;

    public ?int $standardId = null;

    public ?int $assessmentElementId = null;

    public string $website = '';

    public ?TemporaryUploadedFile $file = null;

    public bool $uploaded = false;

    public ?string $syncError = null;

    public ?string $documentMessage = null;

    public function mount(GoogleDriveService $drive): void
    {
        $this->refreshStructure($drive);
    }

    public function refreshStructure(GoogleDriveService $drive): void
    {
        try {
            $drive->syncDatabaseStructureFromDrive();
            $this->syncError = null;
        } catch (Throwable $exception) {
            report($exception);
            $this->syncError = 'Struktur Drive belum dapat diperbarui. Data sinkronisasi terakhir tetap ditampilkan.';
        }
    }

    public function toggleWorkingGroup(int $workingGroupId): void
    {
        $workingGroup = WorkingGroup::query()->where('is_active', true)->findOrFail($workingGroupId);

        if (in_array($workingGroup->id, $this->expandedWorkingGroups, true)) {
            $this->expandedWorkingGroups = array_values(array_diff($this->expandedWorkingGroups, [$workingGroup->id]));
            $standardIds = Standard::query()->where('working_group_id', $workingGroup->id)->pluck('id')->all();
            $this->expandedStandards = array_values(array_diff($this->expandedStandards, $standardIds));

            return;
        }

        $this->expandedWorkingGroups[] = $workingGroup->id;
    }

    public function toggleStandard(int $standardId): void
    {
        $standard = Standard::query()->where('is_active', true)->findOrFail($standardId);

        if (in_array($standard->id, $this->expandedStandards, true)) {
            $this->expandedStandards = array_values(array_diff($this->expandedStandards, [$standard->id]));

            return;
        }

        if (! in_array($standard->working_group_id, $this->expandedWorkingGroups, true)) {
            $this->expandedWorkingGroups[] = $standard->working_group_id;
        }

        $this->expandedStandards[] = $standard->id;
    }

    public function selectElement(int $assessmentElementId): void
    {
        $element = AssessmentElement::query()
            ->where('is_active', true)
            ->with('standard.workingGroup.accreditationGroup')
            ->findOrFail($assessmentElementId);

        $this->assessmentElementId = $element->id;
        $this->standardId = $element->standard_id;
        $this->workingGroupId = $element->standard->working_group_id;
        $this->groupId = $element->standard->workingGroup->accreditation_group_id;
        $this->uploaded = false;
        $this->reset('file');
        $this->resetValidation();
        $this->dispatch('element-selected');
    }

    public function uploadDocument(GoogleDriveService $drive): void
    {
        $key = 'public-upload:'.request()->ip();

        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw ValidationException::withMessages([
                'file' => 'Terlalu banyak unggahan. Silakan coba lagi dalam '.RateLimiter::availableIn($key).' detik.',
            ]);
        }

        RateLimiter::hit($key, 60);

        $validated = $this->validate([
            'groupId' => ['required', 'integer', 'exists:accreditation_groups,id'],
            'workingGroupId' => ['required', 'integer', 'exists:working_groups,id'],
            'standardId' => ['required', 'integer', 'exists:standards,id'],
            'assessmentElementId' => ['required', 'integer', 'exists:assessment_elements,id'],
            'website' => ['nullable', 'max:0'],
            'file' => ['required', 'file', 'max:20480', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png'],
        ], [
            'file.max' => 'Ukuran file maksimal 20 MB.',
            'file.mimes' => 'Format yang diizinkan: PDF, Word, Excel, PowerPoint, JPG, dan PNG.',
            'required' => ':attribute wajib diisi.',
        ], [
            'file' => 'File',
        ]);

        $element = AssessmentElement::query()
            ->whereKey($validated['assessmentElementId'])
            ->where('is_active', true)
            ->whereHas('standard', fn ($query) => $query
                ->whereKey($validated['standardId'])
                ->where('working_group_id', $validated['workingGroupId'])
                ->where('is_active', true)
                ->whereHas('workingGroup', fn ($query) => $query
                    ->where('accreditation_group_id', $validated['groupId'])
                    ->where('is_active', true)))
            ->first();

        if (! $element) {
            throw ValidationException::withMessages(['assessmentElementId' => 'Pilihan struktur dokumen tidak valid.']);
        }

        try {
            $uploadedFile = $this->file;

            if (! $uploadedFile) {
                throw ValidationException::withMessages(['file' => 'File wajib diisi.']);
            }

            $uploaded = $drive->upload(
                $uploadedFile->getRealPath(),
                $uploadedFile->getClientOriginalName(),
                $uploadedFile->getMimeType() ?: 'application/octet-stream',
                $element,
            );

            AccreditationDocument::create([
                'assessment_element_id' => $element->id,
                'uploader_name' => 'Pengunggah publik',
                'uploader_unit' => 'Tidak dicantumkan',
                'uploader_ip_hash' => AccreditationDocument::hashUploaderIp((string) request()->ip()),
                'original_name' => $uploadedFile->getClientOriginalName(),
                'mime_type' => $uploaded['mimeType'],
                'size' => (int) ($uploaded['size'] ?? $uploadedFile->getSize()),
                'drive_file_id' => $uploaded['id'],
                'drive_web_view_link' => $uploaded['webViewLink'] ?? null,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('file', app()->isLocal()
                ? 'Unggahan gagal: '.$exception->getMessage()
                : 'Unggahan gagal. Silakan hubungi admin atau coba kembali.');

            return;
        }

        $this->reset('file');
        $this->uploaded = true;
        $this->documentMessage = 'File berhasil diunggah dan langsung tersedia pada EP ini.';
    }

    public function deleteDocument(int $documentId, GoogleDriveService $drive): void
    {
        $document = AccreditationDocument::query()->findOrFail($documentId);

        abort_unless(
            $document->canBeDeletedFromIp((string) request()->ip()),
            403,
        );

        try {
            $drive->delete($document->drive_file_id);
            $document->delete();
            $this->documentMessage = 'File berhasil dihapus dari Google Drive.';
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('documentAction', app()->isLocal()
                ? 'File gagal dihapus: '.$exception->getMessage()
                : 'File gagal dihapus. Silakan coba kembali.');
        }
    }

    public function uploadAnother(): void
    {
        $this->uploaded = false;
        $this->resetValidation();
    }

    public function clearSelection(): void
    {
        $this->reset('groupId', 'workingGroupId', 'standardId', 'assessmentElementId', 'file', 'uploaded');
        $this->resetValidation();
    }

    public function render(): View
    {
        $expandedWorkingGroups = array_values(array_unique(array_map('intval', $this->expandedWorkingGroups)));
        $expandedStandards = array_values(array_unique(array_map('intval', $this->expandedStandards)));

        $groups = AccreditationGroup::query()
            ->where('is_active', true)
            ->with(['workingGroups' => fn ($query) => $query
                ->where('is_active', true)
                ->when(filled($this->search), fn ($query) => $query->where(fn ($query) => $query
                    ->where('code', 'like', '%'.$this->search.'%')
                    ->orWhere('name', 'like', '%'.$this->search.'%')))
                ->withCount(['standards' => fn ($query) => $query->where('is_active', true)])
                ->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        $workingGroupIds = $groups->flatMap->workingGroups->pluck('id');
        $workingGroupRequiredDocuments = AssessmentElement::query()
            ->join('standards', 'standards.id', '=', 'assessment_elements.standard_id')
            ->whereIn('standards.working_group_id', $workingGroupIds)
            ->where('standards.is_active', true)
            ->where('assessment_elements.is_active', true)
            ->whereNotNull('assessment_elements.required_document_count')
            ->selectRaw('standards.working_group_id as progress_key, SUM(assessment_elements.required_document_count) as aggregate')
            ->groupBy('standards.working_group_id')
            ->pluck('aggregate', 'progress_key');
        $scoredElements = AssessmentElement::query()
            ->whereHas('standard', fn ($query) => $query
                ->whereIn('working_group_id', $workingGroupIds)
                ->where('is_active', true))
            ->where('is_active', true)
            ->whereNotNull('required_document_count')
            ->with('standard:id,working_group_id')
            ->withCount('documents')
            ->get();
        $workingGroupUploadedDocuments = $scoredElements
            ->groupBy(fn (AssessmentElement $element): int => $element->standard->working_group_id)
            ->map(fn (Collection $elements): int => $elements->sum(
                fn (AssessmentElement $element): int => min(
                    $element->documents_count,
                    max(0, $element->required_document_count),
                )
            ));

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
        $standardRequiredDocuments = AssessmentElement::query()
            ->whereIn('standard_id', $visibleStandardIds)
            ->where('is_active', true)
            ->whereNotNull('required_document_count')
            ->selectRaw('standard_id as progress_key, SUM(required_document_count) as aggregate')
            ->groupBy('standard_id')
            ->pluck('aggregate', 'progress_key');
        $standardUploadedDocuments = $scoredElements
            ->whereIn('standard_id', $visibleStandardIds)
            ->groupBy('standard_id')
            ->map(fn (Collection $elements): int => $elements->sum(
                fn (AssessmentElement $element): int => min(
                    $element->documents_count,
                    max(0, $element->required_document_count),
                )
            ));

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

        $visibleElementIds = $elementsByStandard->flatten(1)->pluck('id');
        $deletableDocumentIds = $visibleElementIds->isEmpty()
            ? []
            : AccreditationDocument::query()
                ->whereIn('assessment_element_id', $visibleElementIds)
                ->where('uploader_ip_hash', AccreditationDocument::hashUploaderIp((string) request()->ip()))
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        $selectedElement = $this->assessmentElementId
            ? AssessmentElement::query()->with('standard.workingGroup.accreditationGroup')->find($this->assessmentElementId)
            : null;

        return view('livewire.public-document-upload', compact(
            'groups',
            'standardsByWorkingGroup',
            'elementsByStandard',
            'selectedElement',
            'deletableDocumentIds',
            'workingGroupRequiredDocuments',
            'workingGroupUploadedDocuments',
            'standardRequiredDocuments',
            'standardUploadedDocuments',
        ));
    }
}
