<?php

return [
    'direct_load' => [
        'require_local_infile' => env('IMPORT_DIRECT_LOAD_REQUIRE_LOCAL_INFILE', true),
        'validation_sample_rows' => env('IMPORT_DIRECT_LOAD_VALIDATION_SAMPLE_ROWS', 5000),
        'daily_loan' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_DAILY_LOAN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_DAILY_LOAN_MAX_ROWS', 300000),
        ],
        'simpanan_multipn' => [
            'enabled' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_ENABLED', true),
            'max_rows' => env('IMPORT_DIRECT_LOAD_SIMPANAN_MULTIPN_MAX_ROWS', 300000),
        ],
    ],
];
