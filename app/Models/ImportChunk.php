<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bookkeeping: marks one chunk of an import as committed. Only ever read
 * through its (tenant-scoped) ImportBatch.
 */
class ImportChunk extends Model
{
    public const UPDATED_AT = null;

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
