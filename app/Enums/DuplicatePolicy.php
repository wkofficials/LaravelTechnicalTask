<?php

namespace App\Enums;

/**
 * What to do with a row whose unique key (e.g. email) already exists in the
 * company. Chosen explicitly per import; nothing is overwritten silently.
 *
 * Rows that repeat a key seen earlier in the same file are never written
 * twice: the first occurrence wins, later ones are skipped (Skip) or
 * reported as errors (Reject / Update).
 */
enum DuplicatePolicy: string
{
    case Reject = 'reject';
    case Skip = 'skip';
    case Update = 'update';

    public function label(): string
    {
        return match ($this) {
            self::Reject => 'Reject duplicates',
            self::Skip => 'Skip duplicates',
            self::Update => 'Update existing',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Reject => 'Rows that match an existing record are rejected and listed in the error report.',
            self::Skip => 'Rows that match an existing record are left out; existing records stay unchanged.',
            self::Update => 'Existing records are updated with the non-empty values from the file; new ones are added.',
        };
    }
}
