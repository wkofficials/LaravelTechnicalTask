<?php

namespace App\Models;

use App\Enums\ImportBatchStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportBatch extends Model
{
    /** @use HasFactory<\Database\Factories\ImportBatchFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_filename',
        'file_path',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'row_errors',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ImportBatchStatus::class,
            'row_errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
}
