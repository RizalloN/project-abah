<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'public_access_health' => [
        'enabled' => env('PUBLIC_ACCESS_HEALTH_ENABLED', false),
    ],

    'onlyoffice' => [
        'enabled' => (bool) env('ONLYOFFICE_ENABLED', false),
        'public_url' => rtrim((string) env('ONLYOFFICE_PUBLIC_URL', ''), '/'),
        'internal_url' => rtrim((string) env(
            'ONLYOFFICE_INTERNAL_URL',
            env('ONLYOFFICE_PUBLIC_URL', '')
        ), '/'),
        'app_url' => rtrim((string) env('ONLYOFFICE_APP_URL', env('APP_URL', '')), '/'),
        'jwt_secret' => (string) env('ONLYOFFICE_JWT_SECRET', ''),
        'jwt_header' => (string) env('ONLYOFFICE_JWT_HEADER', 'AuthorizationJwt'),
        'allowed_download_origins' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ONLYOFFICE_ALLOWED_DOWNLOAD_ORIGINS', ''))
        ))),
        'access_ttl_minutes' => max(5, (int) env('ONLYOFFICE_ACCESS_TTL_MINUTES', 1440)),
        'timeout_seconds' => max(5, (int) env('ONLYOFFICE_TIMEOUT_SECONDS', 120)),
        'max_download_bytes' => max(
            1_048_576,
            (int) env('ONLYOFFICE_MAX_DOWNLOAD_BYTES', 52_428_800)
        ),
        'verify_tls' => (bool) env('ONLYOFFICE_VERIFY_TLS', true),
    ],

    'market_share' => [
        'title' => env('MARKET_SHARE_WORKBOOK_TITLE', 'Market Share Office 365'),
        'source_url' => env('MARKET_SHARE_SOURCE_URL'),
        'public_token' => env('MARKET_SHARE_PUBLIC_TOKEN'),
        'cache_path' => env('MARKET_SHARE_CACHE_PATH', 'app/public_workbooks/market-share.xlsx'),
        'cache_minutes' => env('MARKET_SHARE_CACHE_MINUTES', 15),
        'timeout_seconds' => env('MARKET_SHARE_TIMEOUT_SECONDS', 90),
        'workbook_url' => env(
            'MARKET_SHARE_WORKBOOK_URL',
            'https://lin20912662-my.sharepoint.com/:x:/g/personal/rizallon_officeoriku_com/IQANZEs7qfDURKnDfvvRwvtHAaP4z-PSUVSkA4YGnS6Gpio?e=EJGqRy'
        ),
    ],

    'market_share_mapping' => [
        'title' => env('MARKET_SHARE_MAPPING_TITLE', 'Mapping Market Share Google Sheets'),
        'source_url' => env(
            'MARKET_SHARE_MAPPING_SOURCE_URL',
            'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/export?format=xlsx'
        ),
        'public_token' => env('MARKET_SHARE_MAPPING_PUBLIC_TOKEN'),
        'cache_path' => env('MARKET_SHARE_MAPPING_CACHE_PATH', 'app/cache/market-share-mapping.xlsx'),
        'fallback_cache_path' => env('MARKET_SHARE_MAPPING_FALLBACK_CACHE_PATH', 'app/public_workbooks/market-share-mapping.xlsx'),
        'cache_minutes' => env('MARKET_SHARE_MAPPING_CACHE_MINUTES', 15),
        'timeout_seconds' => env('MARKET_SHARE_MAPPING_TIMEOUT_SECONDS', 120),
        'workbook_url' => env(
            'MARKET_SHARE_MAPPING_WORKBOOK_URL',
            env(
                'MARKET_SHARE_MAPPING_URL',
                'https://docs.google.com/spreadsheets/d/1aepYbSA8RAFU7RFUh4vOQ-Rp7xALY9q87uXgn6aVYSE/edit?usp=sharing'
            )
        ),
    ],

    'system_binaries' => [
        'mysql' => env('MYSQL_BINARY'),
        'mysqldump' => env('MYSQLDUMP_BINARY'),
        'gzip' => env('GZIP_BINARY'),
        'awk' => env('AWK_BINARY'),
    ],

    'managed_report_recovery' => [
        'allowed_backup_dirs' => env('MANAGED_REPORT_RECOVERY_ALLOWED_BACKUP_DIRS', ''),
    ],

];
