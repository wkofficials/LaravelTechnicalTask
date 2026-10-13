<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Record extends Model
{
    /** @use HasFactory<\Database\Factories\RecordFactory> */
    use BelongsToCompany, HasFactory;

    protected $fillable = [
        'import_batch_id',
        'name',
        'email',
        'phone',
        'company',
        'city',
        'country',
    ];

    public function importBatch(): BelongsTo
    {
        return $this->belongsTo(ImportBatch::class);
    }
}
