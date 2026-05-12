<?php

/**
 * DISTRIBUTED SHADOW COLUMNS CONFIGURATION
 * Central rule definitions for transformation logic applied across multiple tables
 *
 * This configuration drives the ShadowColumnRuleEngine to ensure consistent
 * transformations across all applicable tables (daily_loan_dinamis, simpanan_multipn, brihc, casa_brilink_web)
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Generated Column Sibling Convention
    |--------------------------------------------------------------------------
    |
    | Deterministic shadow-column siblings use the *_gc suffix. These columns
    | are additive and must not replace the legacy shadow columns until a
    | separate migration explicitly proves source/legacy/generated parity.
    |
    */
    'generated_column_suffix' => '_gc',

    /**
     * Rule definitions: transformation logic + table application
     */
    'rules' => [
        /**
         * CIF Normalization: Remove non-numeric characters from CIF/account identifiers
         * Impact: Enables index seek on cif_normalized columns instead of REGEXP function eval
         */
        'cif_normalization' => [
            'description' => 'Remove non-numeric characters from CIF identifiers',
            'transformation' => 'numeric_only',  // REGEXP_REPLACE([^0-9], '')
            'query_pattern' => "REGEXP_REPLACE(?, '[^0-9]', '')",
            'apply_to_tables' => [
                'daily_loan_dinamis' => [
                    'source_column' => 'CIFNO',
                    'shadow_column' => 'cif_normalized',
                    'priority' => 'CRITICAL',
                    'index_hint' => 'Can use index on cif_normalized instead of function eval',
                ],
                'simpanan_multipn' => [
                    'source_column' => 'CIFNO',
                    'shadow_column' => 'cif_normalized',
                    'priority' => 'HIGH',
                    'index_hint' => 'Enables index seek for Rasio CASA JOIN queries',
                ],
                'brihc' => [
                    'source_column' => 'cifno',
                    'shadow_column' => 'cif_normalized',
                    'priority' => 'HIGH',
                    'index_hint' => 'Replaces per-row REGEXP_REPLACE in JOIN conditions',
                ],
                'casa_brilink_web' => [
                    'source_column' => 'cifno',
                    'shadow_column' => 'cif_normalized',
                    'priority' => 'MEDIUM',
                ],
            ],
            'performance_impact' => '10x speedup on CIF comparisons (eliminates function eval)',
        ],

        /**
         * Account Number Normalization
         */
        'account_normalization' => [
            'description' => 'Normalize account numbers for consistent comparison',
            'transformation' => 'numeric_only',
            'query_pattern' => "REGEXP_REPLACE(?, '[^0-9]', '')",
            'apply_to_tables' => [
                'daily_loan_dinamis' => [
                    'source_column' => 'ACCTNO',
                    'shadow_column' => 'account_normalized',
                    'priority' => 'HIGH',
                ],
                'simpanan_multipn' => [
                    'source_column' => 'ACCTNO',
                    'shadow_column' => 'account_normalized',
                    'priority' => 'HIGH',
                ],
            ],
        ],

        /**
         * Branch Code Normalization
         */
        'branch_normalization' => [
            'description' => 'Normalize branch codes (numeric only, zero-padded)',
            'transformation' => 'numeric_only',
            'query_pattern' => "LPAD(REGEXP_REPLACE(?, '[^0-9]', ''), 5, '0')",
            'apply_to_tables' => [
                'daily_loan_dinamis' => [
                    'source_column' => 'MAINBR',
                    'shadow_column' => 'branch_normalized',
                    'priority' => 'MEDIUM',
                ],
                'brihc' => [
                    'source_column' => 'branch_code',
                    'shadow_column' => 'branch_normalized',
                    'priority' => 'MEDIUM',
                ],
            ],
        ],

        /**
         * Segment Normalization
         */
        'segment_normalization' => [
            'description' => 'Standardize segment codes and descriptions',
            'transformation' => 'upper_trim',
            'query_pattern' => "UPPER(TRIM(?))",
            'apply_to_tables' => [
                'daily_loan_dinamis' => [
                    'source_column' => 'FKSEGMEN',
                    'shadow_column' => 'segment_normalized',
                    'priority' => 'HIGH',
                ],
                'simpanan_multipn' => [
                    'source_column' => 'FKSEGMEN',
                    'shadow_column' => 'segment_normalized',
                    'priority' => 'MEDIUM',
                ],
            ],
        ],

        /**
         * Product Code Normalization
         */
        'product_normalization' => [
            'description' => 'Standardize product codes',
            'transformation' => 'upper_trim',
            'query_pattern' => "UPPER(TRIM(?))",
            'apply_to_tables' => [
                'daily_loan_dinamis' => [
                    'source_column' => 'FKPRODUCT',
                    'shadow_column' => 'product_normalized',
                    'priority' => 'MEDIUM',
                ],
            ],
        ],

        /**
         * Personnel Number Normalization
         */
        'personnel_normalization' => [
            'description' => 'Normalize personnel/officer numbers',
            'transformation' => 'numeric_only',
            'query_pattern' => "REGEXP_REPLACE(?, '[^0-9]', '')",
            'apply_to_tables' => [],
        ],
    ],

    /**
     * Transformation types: definition of what each transformation does
     */
    'transformations' => [
        'numeric_only' => [
            'pattern' => '/[^0-9]/',
            'replacement' => '',
            'description' => 'Keep only digits, remove all non-numeric characters',
            'sql' => "REGEXP_REPLACE(?, '[^0-9]', '')",
        ],
        'upper_trim' => [
            'pattern' => null,
            'replacement' => null,
            'description' => 'Convert to uppercase and trim whitespace',
            'sql' => "UPPER(TRIM(?))",
        ],
        'lower_trim' => [
            'pattern' => null,
            'replacement' => null,
            'description' => 'Convert to lowercase and trim whitespace',
            'sql' => "LOWER(TRIM(?))",
        ],
        'date_to_epoch' => [
            'pattern' => null,
            'replacement' => null,
            'description' => 'Convert date to Unix epoch timestamp',
            'sql' => "UNIX_TIMESTAMP(?)",
        ],
    ],

    /**
     * Migration strategy: how to add columns and backfill
     */
    'migration' => [
        'batch_size' => 5000,
        'chunk_size' => 1000,
        'retry_passes' => 3,
        'max_backoff_seconds' => 1200,
        'completion_threshold' => 95,  // Require 95% completion before snapshot rebuild
    ],

    /**
     * Validation rules: consistency checks
     */
    'validation' => [
        'ensure_no_nulls_on_non_nullable' => true,
        'verify_transformation_consistency' => true,
        'check_index_creation_before_backfill' => true,
        'validate_shadow_matches_source_count' => true,
    ],

    /**
     * Performance monitoring
     */
    'monitoring' => [
        'track_backfill_progress' => true,
        'track_query_performance' => true,
        'alert_on_completion_below_threshold' => true,
        'completion_threshold_percent' => 95,
    ],

    /**
     * Phase roadmap: implementation schedule
     */
    'phases' => [
        'phase_1_foundation' => [
            'name' => 'Foundation Layer',
            'target_date' => '2026-04-29',
            'tasks' => [
                'Create ShadowColumnRuleEngine service',
                'Create DistributedShadowBackfillJob',
                'Create shadow:backfill-table command',
                'Create shadow:status command',
                'Create shadow:validate-consistency command',
            ],
        ],
        'phase_2_simpanan_multipn' => [
            'name' => 'Simpanan MultiPN Optimization',
            'target_date' => '2026-05-06',
            'priority' => 'HIGH',
            'expected_speedup' => '5-10x for Rasio CASA JOIN',
            'tasks' => [
                'Add shadow columns to simpanan_multipn',
                'Backfill existing data',
                'Create indexes on shadow columns',
                'Refactor Rasio CASA queries',
                'Performance test and validate',
            ],
        ],
        'phase_3_brihc' => [
            'name' => 'BRIHC Optimization',
            'target_date' => '2026-05-13',
            'priority' => 'HIGH',
            'expected_speedup' => '3-5x for JOIN operations',
            'tasks' => [
                'Add shadow columns to brihc',
                'Backfill existing data',
                'Refactor JOIN queries',
                'Performance validation',
            ],
        ],
        'phase_4_monitoring' => [
            'name' => 'Monitoring and Optimization',
            'target_date' => '2026-05-20',
            'tasks' => [
                'Create dashboard for shadow column status',
                'Set up automated backfill schedule',
                'Document best practices',
                'Establish SLA for snapshot build times',
            ],
        ],
    ],
];
