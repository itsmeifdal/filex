<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $working_group_id
 * @property string $code
 * @property string $title
 * @property string|null $drive_folder_id
 */
#[Fillable(['working_group_id', 'code', 'title', 'description', 'sort_order', 'drive_folder_id', 'is_active'])]
class Standard extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<WorkingGroup, $this> */
    public function workingGroup(): BelongsTo
    {
        return $this->belongsTo(WorkingGroup::class);
    }

    /** @return HasMany<AssessmentElement, $this> */
    public function assessmentElements(): HasMany
    {
        return $this->hasMany(AssessmentElement::class)->orderBy('sort_order');
    }
}
