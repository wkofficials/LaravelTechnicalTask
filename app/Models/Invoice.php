<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bill for one plan period. Issued, paid and voided only through
 * App\Billing\Billing (platform admins / the daily billing job).
 */
class Invoice extends Model
{
    // Company users only ever resolve their own company's invoices.
    use BelongsToCompany;

    public const STATUS_OPEN = 'open';

    public const STATUS_PAID = 'paid';

    public const STATUS_VOID = 'void';

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function amount(): string
    {
        return Money::format($this->amount_cents, $this->currency);
    }

    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OPEN && $this->due_at->isPast();
    }

    public function statusLabel(): string
    {
        return $this->isOverdue() ? 'Overdue' : ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return match (true) {
            $this->isOverdue() => 'danger',
            $this->status === self::STATUS_PAID => 'success',
            $this->status === self::STATUS_OPEN => 'warning',
            default => 'secondary',
        };
    }
}
