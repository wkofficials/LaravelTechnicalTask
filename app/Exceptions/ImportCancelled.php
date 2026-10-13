<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown between chunks to stop an import the user asked to cancel.
 */
class ImportCancelled extends RuntimeException
{
}
