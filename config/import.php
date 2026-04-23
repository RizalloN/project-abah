<?php

return [
    'queue' => [
        'inline_fallback_grace_seconds' => env('IMPORT_QUEUE_INLINE_FALLBACK_GRACE_SECONDS', 0),
        'inline_start_tables' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_QUEUE_INLINE_START_TABLES', 'lw325_ph'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'snapshot' => [
        'defer_seconds' => env('IMPORT_SNAPSHOT_DEFER_SECONDS', 60),
        'pause_during_import' => env('IMPORT_SNAPSHOT_PAUSE_DURING_IMPORT', true),
        'pause_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_QUEUES', (string) env('REPORT_QUEUE', 'default')))
        ), static fn (string $value): bool => $value !== '')),
        'pause_excluded_queues' => array_values(array_filter(array_map(
            static fn ($value): string => trim((string) $value),
            explode(',', (string) env('IMPORT_SNAPSHOT_PAUSE_EXCLUDED_QUEUES', 'imports-high'))
        ), static fn (string $value): bool => $value !== '')),
    ],

    'direct_load' => [
        'require_local_infile' => env('IMPORT_DIRECT_LOAD_REQUIRE_LOCAL_INFILE', true),
        'validation_sample_rows' => env('IMPORT_DIRECT_LOAD_VALIDATION_SAMPLE_ROWS', 5000),
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
        ],
        'ssa_simpanan' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SSA_SIMPANAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SSA_SIMPANAN_MAX_ROWS', 0),
        ],
        'ssa_pinjaman' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SSA_PINJAMAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SSA_PINJAMAN_MAX_ROWS', 0),
        ],
        'gi405_rec_dh' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_GI405_REC_DH_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_GI405_REC_DH_MAX_ROWS', 0),
        ],
    ],
];
