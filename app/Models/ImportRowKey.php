<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Bookkeeping: a unique key (hashed) accepted earlier in the same import, and
 * the spreadsheet row it came from. Used to catch duplicates within a file.
 */
class ImportRowKey extends Model
{
    public $timestamps = false;

    public static function hash(string $key): string
    {
        return sha1($key);
    }
}
