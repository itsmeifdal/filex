<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property Carbon|null $token_expires_at
 * @property string|null $root_folder_id
 * @property string|null $connected_email
 * @property Carbon|null $structure_synced_at
 * @property Carbon|null $reauthorization_required_at
 */
#[Fillable(['access_token', 'refresh_token', 'token_expires_at', 'root_folder_id', 'connected_email', 'structure_synced_at', 'reauthorization_required_at'])]
class GoogleDriveSetting extends Model
{
    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'structure_synced_at' => 'datetime',
            'reauthorization_required_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }
}
