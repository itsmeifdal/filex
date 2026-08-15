<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $drive_folder_id
 */
#[Fillable(['code', 'name', 'sort_order', 'drive_folder_id', 'is_active'])]
class AccreditationGroup extends Model
{
    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<WorkingGroup, $this> */
    public function workingGroups(): HasMany
    {
        return $this->hasMany(WorkingGroup::class)->orderBy('sort_order');
    }
}
