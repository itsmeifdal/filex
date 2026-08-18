<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $standard_id
 * @property string $code
 * @property string $description
 * @property int|null $required_document_count
 * @property string|null $evidence_notes
 * @property string|null $drive_folder_id
 */
#[Fillable(['standard_id', 'code', 'description', 'required_document_count', 'evidence_notes', 'sort_order', 'drive_folder_id', 'is_active'])]
class AssessmentElement extends Model
{
    protected function casts(): array
    {
        return [
            'required_document_count' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Standard, $this> */
    public function standard(): BelongsTo
    {
        return $this->belongsTo(Standard::class);
    }

    /** @return HasMany<AccreditationDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(AccreditationDocument::class);
    }
}
