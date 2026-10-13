<?php

namespace App\Datasets;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * A supported, developer-defined import target.
 *
 * Spreadsheet columns can only ever be mapped onto the fields a dataset
 * declares here — never onto arbitrary tables or columns.
 */
abstract class Dataset
{
    /**
     * Stable identifier stored on imports and templates.
     */
    abstract public function key(): string;

    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * Bump when fields change, so saved templates can warn they may be stale.
     */
    abstract public function schemaVersion(): int;

    /**
     * @return array<int, Field>
     */
    abstract public function fields(): array;

    /**
     * Field identifying an existing record within a company (used for
     * duplicate detection and updates). Backed by a unique DB index.
     */
    abstract public function uniqueField(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function modelClass(): string;

    /**
     * Singular noun for one row, e.g. "contact".
     */
    abstract public function recordLabel(): string;

    /**
     * Columns covered by the table's FULLTEXT index (MySQL); free-text
     * search looks in exactly these.
     *
     * @return array<int, string>
     */
    abstract public function searchableFields(): array;

    /**
     * Columns the list may be ordered by. Allow-listed so a URL parameter can
     * never order by an arbitrary column; each needs a (company_id, column) index.
     *
     * @return array<int, string>
     */
    public function sortableFields(): array
    {
        return [...$this->fieldKeys(), 'created_at'];
    }

    /**
     * Fields offered as exact-match filters (choose one of the company's
     * existing values). Each needs a (company_id, column) index.
     *
     * @return array<int, string>
     */
    public function filterableFields(): array
    {
        return [];
    }

    /**
     * Columns written to exports, in order.
     *
     * @return array<int, string>
     */
    public function exportableFields(): array
    {
        return [...$this->fieldKeys(), 'created_at', 'updated_at'];
    }

    /**
     * Example rows for the downloadable sample file (field key => value).
     *
     * @return array<int, array<string, string>>
     */
    public function exampleRows(): array
    {
        return [];
    }

    public function columnLabel(string $column): string
    {
        return $this->field($column)?->label ?? Str::headline($column);
    }

    public function field(string $key): ?Field
    {
        foreach ($this->fields() as $field) {
            if ($field->key === $key) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function fieldKeys(): array
    {
        return array_map(fn (Field $field) => $field->key, $this->fields());
    }

    /**
     * Suggest a column for each field by comparing normalized header names.
     * Suggestions only — the user always confirms the mapping.
     *
     * @param  array<int, string|null>  $headers  Column index => header text.
     * @return array<string, int|null>  Field key => column index.
     */
    public function suggestMapping(array $headers): array
    {
        $normalized = array_map(fn ($header) => self::normalizeHeader((string) $header), $headers);
        $taken = [];
        $mapping = [];

        foreach ($this->fields() as $field) {
            $mapping[$field->key] = null;

            foreach ($normalized as $index => $header) {
                if ($header !== '' && ! isset($taken[$index]) && in_array($header, $field->headerMatches(), true)) {
                    $mapping[$field->key] = $index;
                    $taken[$index] = true;
                    break;
                }
            }
        }

        return $mapping;
    }

    /**
     * "E-mail Address " → "emailaddress", so differently written headers match.
     */
    public static function normalizeHeader(string $header): string
    {
        return preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii($header)));
    }
}
