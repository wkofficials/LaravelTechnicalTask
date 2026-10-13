<?php

namespace App\Datasets;

use Illuminate\Support\Str;

/**
 * One importable field of a dataset: how a spreadsheet cell is cleaned up
 * and validated before it may be written to the dataset's table.
 */
final class Field
{
    public const TYPE_STRING = 'string';

    public const TYPE_EMAIL = 'email';

    /**
     * @param  array<int, string>  $aliases  Other header names that suggest this field.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TYPE_STRING,
        public readonly bool $required = false,
        public readonly int $maxLength = 255,
        public readonly array $aliases = [],
    ) {
    }

    /**
     * Trim the raw cell; empty becomes null. Emails are lower-cased so that
     * duplicate detection doesn't depend on letter case.
     */
    public function normalize(mixed $raw): ?string
    {
        if ($raw === null || is_array($raw)) {
            return null;
        }

        $value = trim((string) $raw);

        // Undo our own CSV formula escaping ("'+92…" → "+92…"), so a
        // downloaded error report or export can be uploaded again unchanged.
        if (preg_match('/^\'[=+\-@]/', $value)) {
            $value = substr($value, 1);
        }

        if ($value === '') {
            return null;
        }

        return $this->type === self::TYPE_EMAIL ? Str::lower($value) : $value;
    }

    /**
     * @return array{0: string, 1: string}|null  [error_code, message] or null when valid.
     */
    public function validate(?string $value): ?array
    {
        if ($value === null) {
            return $this->required ? ["{$this->key}_required", "{$this->label} is required."] : null;
        }

        if (mb_strlen($value) > $this->maxLength) {
            return ["{$this->key}_too_long", "{$this->label} is longer than {$this->maxLength} characters."];
        }

        if ($this->type === self::TYPE_EMAIL && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ["{$this->key}_invalid", "{$this->label} is not a valid email address."];
        }

        return null;
    }

    /**
     * Header spellings that should be suggested for this field, normalized.
     *
     * @return array<int, string>
     */
    public function headerMatches(): array
    {
        return array_map(Dataset::normalizeHeader(...), [$this->key, $this->label, ...$this->aliases]);
    }
}
