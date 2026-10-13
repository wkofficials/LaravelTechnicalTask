<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's paid plan (or trial). The company's effective limits always
 * come from companies.plan_id; billing changes that column — on payment,
 * trial start, and downgrade — so limits and billing can't disagree.
 * Changed only through App\Billing\Billing.
 */
class Subscription extends Model
{
    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const INTERVAL_MONTHLY = 'monthly';

    public const INTERVAL_YEARLY = 'yearly';

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'ended_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
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

    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE, self::STATUS_PAST_DUE], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_TRIALING => 'Trial',
            self::STATUS_ACTIVE => $this->cancel_at_period_end ? 'Active (cancels at period end)' : 'Active',
            self::STATUS_PAST_DUE => 'Payment overdue',
            self::STATUS_CANCELLED => 'Ended',
            default => ucfirst($this->status),
        };
    }

    public function statusColor(): string
    {
        return match ($this->status) {
            self::STATUS_ACTIVE => $this->cancel_at_period_end ? 'warning' : 'success',
            self::STATUS_TRIALING => 'info',
            self::STATUS_PAST_DUE => 'danger',
            default => 'secondary',
        };
    }
}
