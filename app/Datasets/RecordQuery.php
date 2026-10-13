<?php

namespace App\Datasets;

use Illuminate\Database\Eloquent\Builder;

/**
 * Search / filter / sort options for listing a dataset's records, cleaned
 * against the dataset's allow-lists. The same object drives the on-screen
 * list and exports, so an export contains exactly what the list showed.
 *
 * Tenant scoping comes from the model's company global scope (or, in queued
 * jobs, the company context the job runs in).
 */
final class RecordQuery
{
    private const MAX_SEARCH_LENGTH = 200;

    /**
     * @param  array<string, string>  $filters  Field key => exact value.
     */
    private function __construct(
        public readonly Dataset $dataset,
        public readonly string $search,
        public readonly array $filters,
        public readonly string $sort,
        public readonly string $direction,
    ) {
    }

    /**
     * Build from untrusted input (query string or stored export options);
     * anything not allow-listed is dropped.
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromInput(Dataset $dataset, array $input): self
    {
        $sort = in_array($input['sort'] ?? null, $dataset->sortableFields(), true) ? $input['sort'] : 'id';
        $direction = ($input['direction'] ?? null) === 'asc' ? 'asc' : 'desc';
        $search = mb_substr(trim((string) (is_string($input['q'] ?? null) ? $input['q'] : '')), 0, self::MAX_SEARCH_LENGTH);

        $filters = [];
        $rawFilters = is_array($input['filter'] ?? null) ? $input['filter'] : [];

        foreach ($dataset->filterableFields() as $field) {
            $value = $rawFilters[$field] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $filters[$field] = trim($value);
            }
        }

        return new self($dataset, $search, $filters, $sort, $direction);
    }

    /**
     * @return array{q: string, filter: array<string, string>, sort: string, direction: string}
     */
    public function toArray(): array
    {
        return ['q' => $this->search, 'filter' => $this->filters, 'sort' => $this->sort, 'direction' => $this->direction];
    }

    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->filters !== [];
    }

    public function build(): Builder
    {
        $model = $this->dataset->modelClass();
        $query = $model::query();

        if ($this->search !== '') {
            $this->applySearch($query);
        }

        foreach ($this->filters as $field => $value) {
            $query->where($field, $value);
        }

        // id as tie-breaker keeps pagination stable on equal values.
        return $query->orderBy($this->sort, $this->direction)->orderBy('id', $this->direction);
    }

    /**
     * MySQL/MariaDB gets a real indexed FULLTEXT boolean-mode search, which
     * stays fast at 100k+ rows. Other drivers (SQLite in tests) fall back to
     * a plain LIKE scan — same behavior, no index, only used outside MySQL.
     */
    private function applySearch(Builder $query): void
    {
        $columns = $this->dataset->searchableFields();

        if ($query->getConnection()->getDriverName() === 'mysql') {
            $booleanSearch = $this->toBooleanSearchExpression($this->search);

            if ($booleanSearch !== '') {
                $query->whereFullText($columns, $booleanSearch, ['mode' => 'boolean']);
            }

            return;
        }

        $query->where(function (Builder $query) use ($columns) {
            foreach ($columns as $column) {
                $query->orWhere($column, 'like', '%'.$this->search.'%');
            }
        });
    }

    /**
     * Turn free-text user input into a MySQL boolean-mode fulltext
     * expression: each word becomes a required prefix match (+word*),
     * with boolean-mode operator characters stripped from the input first.
     */
    private function toBooleanSearchExpression(string $term): string
    {
        $clean = preg_replace('/[+\-<>()~*"@]+/', ' ', $term);
        $words = array_filter(explode(' ', $clean), fn ($word) => $word !== '');

        return implode(' ', array_map(fn ($word) => '+'.$word.'*', $words));
    }
}
