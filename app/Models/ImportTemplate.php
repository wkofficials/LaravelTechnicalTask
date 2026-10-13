<?php

namespace App\Models;

use App\Datasets\Dataset;
use App\Enums\DuplicatePolicy;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A company's saved column mapping for one dataset.
 *
 * mapping: field key => header name in the file (matched by normalized name,
 * so it still works when columns move).
 */
class ImportTemplate extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'dataset_key',
        'name',
        'mapping',
        'duplicate_policy',
        'schema_version',
    ];

    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'duplicate_policy' => DuplicatePolicy::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isOutdatedFor(Dataset $dataset): bool
    {
        return $this->schema_version !== $dataset->schemaVersion();
    }

    /**
     * Apply this template to a file's headers.
     *
     * @param  array<int, string|null>  $headers
     * @return array{mapping: array<string, int|null>, missing: array<int, string>}
     *   Field key => column index, plus the template headers not found in the file.
     */
    public function resolveAgainst(array $headers, Dataset $dataset): array
    {
        $columnsByHeader = [];

        foreach ($headers as $index => $header) {
            $columnsByHeader[Dataset::normalizeHeader((string) $header)] ??= $index;
        }

        $mapping = array_fill_keys($dataset->fieldKeys(), null);
        $missing = [];

        foreach ($this->mapping ?? [] as $fieldKey => $header) {
            if (! array_key_exists($fieldKey, $mapping)) {
                continue; // field no longer exists in the dataset
            }

            $index = $columnsByHeader[Dataset::normalizeHeader((string) $header)] ?? null;

            $index === null ? $missing[] = $header : $mapping[$fieldKey] = $index;
        }

        return ['mapping' => $mapping, 'missing' => $missing];
    }
}
