<?php

namespace App\Models;

use App\Datasets\Dataset;
use App\Datasets\DatasetRegistry;
use App\Enums\DuplicatePolicy;
use App\Enums\ImportBatchStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class ImportBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ImportBatchFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'user_id',
        'dataset_key',
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'duplicate_policy' => DuplicatePolicy::class,
            // Column index => header text, and the first few data rows.
            'headers' => 'array',
            'preview_rows' => 'array',
            // Field key => column index, as confirmed by the user.
            'mapping' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'cancel_requested_at' => 'datetime',
            'file_deleted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function records(): HasMany
    {
        return $this->hasMany(Record::class);
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(ImportChunk::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ImportTemplate::class);
    }

    public function dataset(): Dataset
    {
        return app(DatasetRegistry::class)->getOrFail($this->dataset_key);
    }

    /**
     * Header text for a column, with a readable fallback for blank headers.
     */
    public function columnLabel(int $index): string
    {
        $header = trim((string) ($this->headers[$index] ?? ''));
        $letter = Coordinate::stringFromColumnIndex($index + 1);

        return $header === '' ? "Column {$letter} (no heading)" : $header;
    }

    /**
     * Move to a final status, unless the batch already reached one (so a late
     * failure can't overwrite a cancellation, or vice versa).
     */
    public function finishWith(ImportBatchStatus $status, string $message): bool
    {
        return (bool) static::withoutGlobalScopes()
            ->whereKey($this->id)
            ->whereNotIn('status', array_map(fn ($s) => $s->value, ImportBatchStatus::finished()))
            ->update([
                'status' => $status->value,
                'error_message' => $message,
                'finished_at' => now(),
            ]);
    }

    /**
     * Imports the user may see inside the current company: their own, or all
     * of them with the "view company imports" permission.
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('view company imports')) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * Rows written to the dataset (new + updated).
     */
    public function importedRows(): int
    {
        return $this->inserted_rows + $this->updated_rows;
    }

    /**
     * Processing progress, or null while the total row count is unknown.
     */
    public function percentage(): ?int
    {
        if (! $this->total_rows) {
            return $this->status->isFinished() && $this->status !== ImportBatchStatus::Failed ? 100 : null;
        }

        return (int) min(100, round($this->processed_rows / $this->total_rows * 100));
    }

    public function canBeCancelled(): bool
    {
        return ! $this->status->isFinished() && $this->cancel_requested_at === null;
    }
}
