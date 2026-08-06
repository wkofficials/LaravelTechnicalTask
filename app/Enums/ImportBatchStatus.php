<?php

namespace App\Enums;

enum ImportBatchStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case CompletedWithErrors = 'completed_with_errors';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Completed => 'Completed',
            self::CompletedWithErrors => 'Completed with errors',
            self::Failed => 'Failed',
        };
    }

    public function isFinished(): bool
    {
        return match ($this) {
            self::Completed, self::CompletedWithErrors, self::Failed => true,
            self::Pending, self::Processing => false,
        };
    }
}
