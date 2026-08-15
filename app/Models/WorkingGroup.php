<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $accreditation_group_id
 * @property string $code
 * @property string $name
 * @property string|null $drive_folder_id
 */
#[Fillable(['accreditation_group_id', 'code', 'name', 'description', 'sort_order', 'drive_folder_id', 'is_active'])]
class WorkingGroup extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<AccreditationGroup, $this> */
    public function accreditationGroup(): BelongsTo
    {
        return $this->belongsTo(AccreditationGroup::class);
    }

    /** @return HasMany<Standard, $this> */
    public function standards(): HasMany
    {
        return $this->hasMany(Standard::class)->orderBy('sort_order');
    }
}
