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

    /*
    |--------------------------------------------------------------------------
    | Layout
    |--------------------------------------------------------------------------
    |
    | "chunked" writes one gzip part per table, splits the largest tables per
    | period and hard-links parts whose period identity (row count and primary
    | key range) did not change since the previous verified backup. "single"
    | keeps the original one-file logical dump.
    |
    */
    'mode' => (string) env('DATABASE_DAILY_BACKUP_MODE', 'chunked'),

    // Concurrent mysqldump processes; bounded to protect the live server.
    'workers' => min(6, max(1, (int) env('DATABASE_DAILY_BACKUP_WORKERS', 3))),

    // Table => period column. Only these tables are split and reused per period.
    'chunked_tables' => [
        'daily_loan_dinamis' => 'periode',
        'simpanan_multipn' => 'posisi',
        'dashboard_pinjaman_chart_periodik_snapshots' => 'periode',
        'dashboard_pinjaman_snapshots' => 'periode',
        'lw325_ph' => 'periode',
    ],

    // Structure is kept, rows are transient (queues, cache, sessions, staging).
    'schema_only_tables' => [
        'cache',
        'cache_locks',
        'sessions',
        'jobs',
        'job_batches',
        'failed_jobs',
    ],
    'schema_only_prefixes' => ['tmp_bulk_csv_stage_'],

    // A reused part is dumped again after this many days; the nightly refresh
    // volume is spread so every part is re-read at least once per window.
    'max_reuse_days' => max(1, (int) env('DATABASE_DAILY_BACKUP_MAX_REUSE_DAYS', 7)),

    // Wait (minutes) for running imports before taking the backup.
    'import_wait_minutes' => max(0, (int) env('DATABASE_DAILY_BACKUP_IMPORT_WAIT_MINUTES', 30)),

    'mysql_binary' => (string) env(
        'MYSQL_BINARY',
        'D:\\XAMPP\\mysql\\bin\\mysql.exe'
    ),
];
