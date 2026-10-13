<?php

namespace App\Models;

use App\Datasets\Dataset;
use App\Datasets\DatasetRegistry;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A CSV export of a dataset's records, built in the background. The file is
 * private to the user who requested it and is deleted when it expires.
 */
class DataExport extends Model
{
    use BelongsToCompany;

    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    /**
     * How long a finished file stays downloadable.
     */
    public const KEEP_FOR_HOURS = 24;

    protected $fillable = [
        'dataset_key',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'finished_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function dataset(): Dataset
    {
        return app(DatasetRegistry::class)->getOrFail($this->dataset_key);
    }

    public function isRunning(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    public function isDownloadable(): bool
    {
        return $this->status === self::STATUS_COMPLETED
            && $this->file_path !== null
            && ($this->expires_at === null || $this->expires_at->isFuture());
    }
}
