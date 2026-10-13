<?php

namespace App\Policies;

use App\Enums\ImportBatchStatus;
use App\Models\ImportBatch;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;

class ImportBatchPolicy
{
    public function __construct(private readonly CurrentCompany $currentCompany)
    {
    }

    /**
     * Determine whether the user can view the batch's status.
     *
     * The batch must belong to the active company (defence in depth on top
     * of the tenant query scope); within it, users see their own uploads and
     * anyone with "view company imports" sees every upload.
     */
    public function view(User $user, ImportBatch $importBatch): bool
    {
        if ($importBatch->company_id !== $this->currentCompany->id()) {
            return false;
        }

        return $user->id === $importBatch->user_id || $user->can('view company imports');
    }

    /**
     * Confirming the column mapping is the step that writes data, so it is
     * only possible while the import is waiting for it.
     */
    public function confirm(User $user, ImportBatch $importBatch): bool
    {
        return $this->view($user, $importBatch)
            && $importBatch->status === ImportBatchStatus::AwaitingConfirmation;
    }

    /**
     * Whoever may see an import may also stop it while it is still running.
     */
    public function cancel(User $user, ImportBatch $importBatch): bool
    {
        return $this->view($user, $importBatch) && $importBatch->canBeCancelled();
    }
}
