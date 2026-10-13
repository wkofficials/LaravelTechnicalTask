<?php

namespace App\Support;

/**
 * Amounts are stored as integer minor units (cents) to avoid float rounding.
 */
class Money
{
    public static function format(?int $cents, string $currency): string
    {
        if ($cents === null) {
            return '—';
        }

        return $currency.' '.number_format($cents / 100, 2);
    }

    /**
     * "49.99" → 4999. Null/empty input stays null.
     */
    public static function toCents(mixed $amount): ?int
    {
        if ($amount === null || $amount === '') {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }
}
