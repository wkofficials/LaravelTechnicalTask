<?php

return [

    /*
    | Rows read and committed per chunk. Bigger chunks are faster but use more
    | memory per worker; see docs/qa-report.md for measurements. Each import
    | records the size it started with, so changing this never affects
    | imports that are already running or being retried.
    */
    'chunk_size' => (int) env('IMPORT_CHUNK_SIZE', 1000),

    /*
    | Days to keep uploaded original files of finished imports. The import's
    | history, counters and error report are kept; only the file is deleted.
    */
    'file_retention_days' => (int) env('IMPORT_FILE_RETENTION_DAYS', 30),

    /*
    | Imports left waiting for column-mapping confirmation this long are
    | cancelled and their files deleted.
    */
    'abandon_after_days' => (int) env('IMPORT_ABANDON_AFTER_DAYS', 7),

];
