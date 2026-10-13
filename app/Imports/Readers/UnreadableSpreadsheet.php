<?php

namespace App\Imports\Readers;

use RuntimeException;

/**
 * The file itself can't be read (corrupt, wrong format, protected, or
 * deliberately abusive). Retrying won't help, so jobs fail immediately.
 */
class UnreadableSpreadsheet extends RuntimeException
{
}
