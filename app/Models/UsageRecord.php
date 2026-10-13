<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One metered usage event. Written with an idempotency key so the same work
 * (e.g. a retried import chunk) can never be counted twice.
 */
class UsageRecord extends Model
{
    public const METRIC_IMPORT_ROWS = 'import_rows';

    public const UPDATED_AT = null;
}
