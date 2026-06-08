<?php

return [
    'cache_store' => env('IMPORT_CACHE_STORE', 'file'),

    'queue' => [
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
        'max_defer_attempts' => env('IMPORT_SNAPSHOT_MAX_DEFER_ATTEMPTS', 30),
        'pause_during_import' => env('IMPORT_SNAPSHOT_PAUSE_DURING_IMPORT', true),
        'pause_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_QUEUES', 'snapshots-parallel,shadow-backfill'))
        ), static fn (string $value): bool => $value !== '')),
        'pause_excluded_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_EXCLUDED_QUEUES', 'imports-high,imports-daily-loan,default,reports-low'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'excel_stage_idle_timeout_seconds' => env('IMPORT_EXCEL_STAGE_IDLE_TIMEOUT_SECONDS', 300),

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
