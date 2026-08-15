<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property int $assessment_element_id
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property string $drive_file_id
 * @property string|null $uploader_ip_hash
 * @property string $status
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 */
#[Fillable(['assessment_element_id', 'uploader_name', 'uploader_unit', 'uploader_ip_hash', 'original_name', 'mime_type', 'size', 'drive_file_id', 'drive_web_view_link', 'status', 'review_notes', 'reviewed_by', 'reviewed_at'])]
class AccreditationDocument extends Model
{
    protected static function booted(): void
    {
        static::saving(function (self $document): void {
            if (! $document->isDirty('status')) {
                return;
            }

            $document->reviewed_by = $document->status === 'pending' ? null : auth()->user()?->id;
            $document->reviewed_at = $document->status === 'pending' ? null : Carbon::now();
        });
    }

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public static function hashUploaderIp(string $ipAddress): string
    {
        $key = config('app.key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('APP_KEY wajib tersedia untuk melindungi identitas pengunggah.');
        }

        return hash_hmac('sha256', $ipAddress, $key);
    }

    public function canBeDeletedFromIp(string $ipAddress): bool
    {
        return is_string($this->uploader_ip_hash)
            && hash_equals($this->uploader_ip_hash, self::hashUploaderIp($ipAddress));
    }

    /** @return BelongsTo<AssessmentElement, $this> */
    public function assessmentElement(): BelongsTo
    {
        return $this->belongsTo(AssessmentElement::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
