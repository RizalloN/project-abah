<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Daily database backup
    |--------------------------------------------------------------------------
    |
    | The scheduled command is idempotent per calendar day. It streams the
    | logical dump directly into gzip, commits the completed folder atomically,
    | and only then prunes older managed daily backups.
    |
    */
    'enabled' => (bool) env('DATABASE_DAILY_BACKUP_ENABLED', true),

    'directory' => (string) env(
        'DATABASE_DAILY_BACKUP_DIRECTORY',
        'D:\\BACKUP PROJECT ABAH'
    ),

    'retention_count' => max(1, (int) env('DATABASE_DAILY_BACKUP_RETENTION_COUNT', 1)),

    'compression_level' => min(
        9,
        max(1, (int) env('DATABASE_DAILY_BACKUP_COMPRESSION_LEVEL', 4))
    ),

    'min_free_space_bytes' => max(
        0,
        (int) env('DATABASE_DAILY_BACKUP_MIN_FREE_BYTES', 20 * 1024 * 1024 * 1024)
    ),

    'mysqldump_binary' => (string) env(
        'MYSQLDUMP_BINARY',
        'D:\\XAMPP\\mysql\\bin\\mysqldump.exe'
    ),

    'folder_prefix' => 'backup project-abah',
    'timezone' => (string) env('APP_TIMEZONE', 'Asia/Jakarta'),
];
