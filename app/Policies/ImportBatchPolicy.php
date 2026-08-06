<?php

namespace App\Policies;

use App\Models\ImportBatch;
use App\Models\User;

class ImportBatchPolicy
{
    /**
     * Determine whether the user can view the batch's status.
     */
    public function view(User $user, ImportBatch $importBatch): bool
    {
        return $user->id === $importBatch->user_id || $user->hasRole('admin');
    }
}
