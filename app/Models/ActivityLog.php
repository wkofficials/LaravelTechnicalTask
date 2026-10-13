<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An audit trail entry. company_id is null for platform-wide actions.
 * Written through App\Support\Audit; never updated.
 */
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * "import.confirmed" → "Import confirmed".
     */
    public function actionLabel(): string
    {
        return ucfirst(str_replace(['.', '_'], ' ', $this->action));
    }
}
