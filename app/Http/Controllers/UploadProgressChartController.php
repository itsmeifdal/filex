<?php

namespace App\Http\Controllers;

use App\Models\AssessmentElement;
use App\Models\WorkingGroup;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UploadProgressChartController extends Controller
{
    public function __invoke(): View
    {
        $workingGroups = WorkingGroup::query()
            ->where('is_active', true)
            ->whereHas('accreditationGroup', fn ($query) => $query->where('is_active', true))
            ->with('accreditationGroup:id,name')
            ->get(['id', 'accreditation_group_id', 'code', 'name']);

        $workingGroupIds = $workingGroups->pluck('id');

        $requiredDocuments = AssessmentElement::query()
            ->join('standards', 'standards.id', '=', 'assessment_elements.standard_id')
            ->whereIn('standards.working_group_id', $workingGroupIds)
            ->where('standards.is_active', true)
            ->where('assessment_elements.is_active', true)
            ->whereNotNull('assessment_elements.required_document_count')
            ->selectRaw('standards.working_group_id as progress_key, SUM(assessment_elements.required_document_count) as aggregate')
            ->groupBy('standards.working_group_id')
            ->pluck('aggregate', 'progress_key');

        $uploadedDocuments = AssessmentElement::query()
            ->whereHas('standard', fn ($query) => $query
                ->whereIn('working_group_id', $workingGroupIds)
                ->where('is_active', true))
            ->where('is_active', true)
            ->whereNotNull('required_document_count')
            ->with('standard:id,working_group_id')
            ->withCount('documents')
            ->get()
            ->groupBy(fn (AssessmentElement $element): int => $element->standard->working_group_id)
            ->map(fn (Collection $elements): int => $elements->sum(
                fn (AssessmentElement $element): int => min(
                    $element->documents_count,
                    max(0, $element->required_document_count),
                ),
            ));

        $progressItems = $workingGroups
            ->map(function (WorkingGroup $workingGroup) use ($requiredDocuments, $uploadedDocuments): array {
                $required = (int) $requiredDocuments->get($workingGroup->id, 0);
                $uploaded = (int) $uploadedDocuments->get($workingGroup->id, 0);
                $percentage = $required > 0
                    ? round(min(100, ($uploaded / $required) * 100), 1)
                    : 0;

                // Keep the chart easy to scan in 10% bands, but never imply that
                // an incomplete working group is fully complete.
                $displayPercentage = $uploaded >= $required
                    ? 100
                    : (int) round($percentage / 10) * 10;

                if ($displayPercentage === 100 && $uploaded < $required) {
                    $displayPercentage = (int) floor($percentage);
                }

                return [
                    'group' => $workingGroup->accreditationGroup->name,
                    'code' => $workingGroup->code,
                    'name' => $workingGroup->name,
                    'uploaded' => $uploaded,
                    'required' => $required,
                    'percentage' => $percentage,
                    'display_percentage' => $displayPercentage,
                ];
            })
            ->sortBy([
                ['percentage', 'desc'],
                ['name', 'asc'],
            ])
            ->values();

        return view('chart', compact('progressItems'));
    }
}
