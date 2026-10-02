<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ControlsAccreditationAccess;
use Illuminate\Database\Eloquent\Model;

class AccreditationDocumentPolicy
{
    use ControlsAccreditationAccess;

    /**
     * Assesor internal dapat mengubah status verifikasi dokumen, tetapi tidak
     * mendapatkan hak untuk mengubah struktur atau menghapus dokumen.
     */
    public function update(User $user, Model $model): bool
    {
        return $user->is_active && in_array($user->role, ['admin', 'surveyor'], true);
    }
}
