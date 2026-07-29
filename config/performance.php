<?php

return [
    'database' => [
        'runtime_tuning_enabled' => env('PERFORMANCE_DATABASE_RUNTIME_TUNING', true),
        'buffer_pool_mb' => env('PERFORMANCE_DATABASE_BUFFER_POOL_MB', 4096),
        'slow_query_seconds' => env('PERFORMANCE_DATABASE_SLOW_QUERY_SECONDS', 2),
        'slow_query_min_examined_rows' => env('PERFORMANCE_DATABASE_SLOW_QUERY_MIN_EXAMINED_ROWS', 1000),
    ],

    'request_monitoring' => [
        'enabled' => env('PERFORMANCE_REQUEST_MONITORING', true),
        'slow_request_ms' => env('PERFORMANCE_SLOW_REQUEST_MS', 1000),
        'slow_query_total_ms' => env('PERFORMANCE_SLOW_QUERY_TOTAL_MS', 500),
    ],

    'log_maintenance' => [
        'max_bytes' => env('LOG_MAINTENANCE_MAX_BYTES', 33554432),
        'keep_archives' => env('LOG_MAINTENANCE_KEEP_ARCHIVES', 7),
    ],
];
