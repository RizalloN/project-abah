<?php

return [
    'cache_store' => env('IMPORT_CACHE_STORE', 'file'),

    'python_binary' => env('IMPORT_PYTHON_BIN'),

    'queue' => [
        'import_queue' => env('IMPORT_QUEUE', 'imports-high'),
        'daily_loan_queue' => env('IMPORT_DAILY_LOAN_QUEUE', 'imports-daily-loan'),
        'inline_fallback_grace_seconds' => env('IMPORT_QUEUE_INLINE_FALLBACK_GRACE_SECONDS', 0),
        'zero_progress_recovery_minutes' => env('IMPORT_ZERO_PROGRESS_RECOVERY_MINUTES', 5),
        'inline_start_tables' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_QUEUE_INLINE_START_TABLES', 'lw325_ph'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'snapshot' => [
        'enable_analyze_table' => env('SNAPSHOT_ENABLE_ANALYZE_TABLE', false),
        'defer_seconds' => env('IMPORT_SNAPSHOT_DEFER_SECONDS', 60),
        'retry_window_hours' => env('IMPORT_SNAPSHOT_RETRY_WINDOW_HOURS', 24),
        'pause_during_import' => env('IMPORT_SNAPSHOT_PAUSE_DURING_IMPORT', true),
        'pause_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_QUEUES', 'snapshots-priority,snapshots-parallel,shadow-backfill'))
        ), static fn (string $value): bool => $value !== '')),
        'pause_excluded_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_EXCLUDED_QUEUES', 'imports-high,imports-daily-loan,default,reports-low'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'health' => [
        'lock_wait_kill_seconds' => env('IMPORT_HEALTH_LOCK_WAIT_KILL_SECONDS', 300),
        'generic_query_kill_seconds' => env('IMPORT_HEALTH_GENERIC_QUERY_KILL_SECONDS', 3600),
    ],

    'excel_init_timeout_seconds' => env('IMPORT_EXCEL_INIT_TIMEOUT_SECONDS', 60),
    'excel_stage_idle_timeout_seconds' => env('IMPORT_EXCEL_STAGE_IDLE_TIMEOUT_SECONDS', 300),

    'security' => [
        'upload_max_bytes' => env('IMPORT_UPLOAD_MAX_BYTES', 4 * 1024 * 1024 * 1024),
        'archive_max_files' => env('IMPORT_ARCHIVE_MAX_FILES', 100),
        'archive_max_expanded_bytes' => env('IMPORT_ARCHIVE_MAX_EXPANDED_BYTES', 8 * 1024 * 1024 * 1024),
        'archive_timeout_seconds' => env('IMPORT_ARCHIVE_TIMEOUT_SECONDS', 300),
        'backend_source_roots' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_BACKEND_SOURCE_ROOTS', storage_path('app/backend-imports')))
        ), static fn (string $value): bool => $value !== '')),
        'seven_zip_binary' => env(
            'IMPORT_SEVEN_ZIP_BINARY',
            PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\7-Zip\\7z.exe' : '7z'
        ),
    ],

    'direct_load' => [
        'require_local_infile' => env('IMPORT_DIRECT_LOAD_REQUIRE_LOCAL_INFILE', true),
        'validation_sample_rows' => env('IMPORT_DIRECT_LOAD_VALIDATION_SAMPLE_ROWS', 5000),
        'table_write_lock_wait_seconds' => env('IMPORT_TABLE_WRITE_LOCK_WAIT_SECONDS', 300),
        'snapshot_delete_lock_wait_seconds' => env('IMPORT_SNAPSHOT_DELETE_LOCK_WAIT_SECONDS', 8),
        'fallback_chunk_lines' => env('IMPORT_DIRECT_LOAD_FALLBACK_CHUNK_LINES', 20000),
        'fallback_insert_batch_size' => env('IMPORT_DIRECT_LOAD_FALLBACK_INSERT_BATCH_SIZE', 2000),
        'daily_loan' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_DAILY_LOAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_DAILY_LOAN_MAX_ROWS', 0),
        ],
        'simpanan_multipn' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_MAX_ROWS', 0),
            'balance_crosscheck_max_rows' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_BALANCE_CROSSCHECK_MAX_ROWS', 50000),
            'content_lock_seconds' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_CONTENT_LOCK_SECONDS', 1800),
            'orphan_grace_seconds' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_ORPHAN_GRACE_SECONDS', 180),
        ],
        'ssa_simpanan' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SSA_SIMPANAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SSA_SIMPANAN_MAX_ROWS', 0),
        ],
        'hourly_dpk' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_HOURLY_DPK_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_HOURLY_DPK_MAX_ROWS', 0),
        ],
        'ssa_pinjaman' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SSA_PINJAMAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SSA_PINJAMAN_MAX_ROWS', 0),
        ],
        'gi405_recovery' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_GI405_REC_DH_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_GI405_REC_DH_MAX_ROWS', 0),
        ],
    ],
];
