<?php

namespace App\Enums;

/**
 * Analyzing → AwaitingConfirmation → Pending (queued) → Processing →
 * Completed / CompletedWithErrors / Failed / Cancelled.
 */
enum ImportBatchStatus: string
{
    case Analyzing = 'analyzing';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Analyzing => 'Analyzing file',
            self::AwaitingConfirmation => 'Awaiting confirmation',
            self::Pending => 'Queued',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with errors',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    /**
     * Bootstrap contextual colour for badges.
     */
    public function color(): string
    {
        return match ($this) {
            self::Analyzing, self::Pending => 'secondary',
            self::AwaitingConfirmation => 'primary',
            self::Processing => 'info',
            self::Completed => 'success',
            self::CompletedWithErrors => 'warning',
            self::Failed => 'danger',
            self::Cancelled => 'dark',
        };
    }

    public function isFinished(): bool
    {
        return in_array($this, self::finished(), true);
    }

    /**
     * States in which no worker has started writing rows yet, so the import
     * can be cancelled on the spot.
     *
     * @return array<int, self>
     */
    public static function notStarted(): array
    {
        return [self::Analyzing, self::AwaitingConfirmation, self::Pending];
    }

    /**
     * @return array<int, self>
     */
    public static function finished(): array
    {
        return [self::Completed, self::CompletedWithErrors, self::Failed, self::Cancelled];
    }
}
