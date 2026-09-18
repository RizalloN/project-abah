# Domain: database

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=database --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 2 |
| file | 153 |
| function | 374 |
| method | 176 |
| unresolved_symbol | 10 |
| route | 2 |
| class | 16 |
| table | 3 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DailyDatabaseBackupService` | class | 39 | `app/Services/DailyDatabaseBackupService.php:12` |
| `ManagedReportBackupRecoveryService` | class | 33 | `app/Services/ManagedReportBackupRecoveryService.php:14` |
| `backup` | method | 31 | `app/Services/DailyDatabaseBackupService.php:37` |
| `up` | function | 28 | `database/migrations/2026_04_01_000003_main_report_data.php:9` |
| `DatabaseBackupService` | class | 26 | `app/Services/DatabaseBackupService.php:11` |
| `up` | function | 24 | `database/migrations/2026_04_01_000001_initial_core_tables.php:10` |
| `ProgressiveBackupCommand` | class | 23 | `app/Console/Commands/ProgressiveBackupCommand.php:12` |
| `information_schema.statistics` | table | 23 | - |
| `DailyDatabaseBackupServiceTest` | class | 18 | `tests/Unit/DailyDatabaseBackupServiceTest.php:16` |
| `up` | function | 18 | `database/migrations/2026_04_01_000005_dashboard_snapshots_and_triggers.php:10` |
| `CachedMySqlSchemaBuilder` | class | 17 | `app/Support/CachedMySqlSchemaBuilder.php:9` |
| `SchemaMetadataCache` | class | 16 | `app/Support/SchemaMetadataCache.php:9` |
| `invalidate` | method | 15 | `app/Support/SchemaMetadataCache.php:62` |
| `extractTableSqlFromBackup` | method | 14 | `app/Services/ManagedReportBackupRecoveryService.php:172` |
| `performOptimizedBackup` | method | 13 | `app/Console/Commands/ProgressiveBackupCommand.php:107` |
| `handle` | method | 12 | `app/Console/Commands/ProgressiveBackupCommand.php:17` |
| `recoverReportTable` | method | 12 | `app/Services/ManagedReportBackupRecoveryService.php:25` |
| `up` | function | 12 | `database/migrations/2026_04_01_000004_secondary_ssa_cognos_reports.php:10` |
| `CompressedDatabaseDumpRunner` | class | 11 | `app/Services/DatabaseBackup/CompressedDatabaseDumpRunner.php:9` |
| `date` | method | 11 | `tests/Unit/DailyDatabaseBackupServiceTest.php:394` |
| `inspectManagedBackupDirectory` | method | 11 | `app/Services/DailyDatabaseBackupService.php:824` |
| `test_retention_keeps_only_latest_valid_backup_and_preserves_unrelated_content` | method | 11 | `tests/Unit/DailyDatabaseBackupServiceTest.php:127` |
| `createManagedBackup` | method | 10 | `tests/Unit/DailyDatabaseBackupServiceTest.php:274` |
| `put` | method | 10 | `app/Support/DatabaseBackupStatusStore.php:16` |
| `test_backup_is_published_atomically_with_a_verified_manifest_and_matching_hashes` | method | 10 | `tests/Unit/DailyDatabaseBackupServiceTest.php:77` |
| `up` | function | 10 | `database/migrations/2026_04_27_optimize_import_indexes.php:20` |
| `FileManagementBackupStatusTest` | class | 9 | `tests/Unit/FileManagementBackupStatusTest.php:13` |
| `file-management.database-backup` | route | 9 | - |
| `getColumnListing` | method | 9 | `app/Support/SchemaMetadataCache.php:54` |
| `importSqlFileIntoCurrentDatabase` | method | 9 | `app/Services/ManagedReportBackupRecoveryService.php:306` |

## Route Nodes

- `file-management.database-backup` - `file-management/database-backup`
- `file-management.database-backup.status` - `file-management/database-backup/{backupId}/status`

## Class Nodes

- `BackupDatabaseDailyCommand` - `app/Console/Commands/BackupDatabaseDailyCommand.php`
- `BackupDatabaseDailyCommandTest` - `tests/Feature/BackupDatabaseDailyCommandTest.php`
- `CachedMySqlSchemaBuilder` - `app/Support/CachedMySqlSchemaBuilder.php`
- `CompressedDatabaseDumpRunner` - `app/Services/DatabaseBackup/CompressedDatabaseDumpRunner.php`
- `DailyDatabaseBackupLauncherTest` - `tests/Unit/DailyDatabaseBackupLauncherTest.php`
- `DailyDatabaseBackupService` - `app/Services/DailyDatabaseBackupService.php`
- `DailyDatabaseBackupServiceTest` - `tests/Unit/DailyDatabaseBackupServiceTest.php`
- `DatabaseBackupService` - `app/Services/DatabaseBackupService.php`
- `DatabaseBackupStatusStore` - `app/Support/DatabaseBackupStatusStore.php`
- `DatabaseSeeder` - `database/seeders/DatabaseSeeder.php`
- `FileManagementBackupStatusTest` - `tests/Unit/FileManagementBackupStatusTest.php`
- `ManagedReportBackupRecoveryService` - `app/Services/ManagedReportBackupRecoveryService.php`
- `ProgressiveBackupCommand` - `app/Console/Commands/ProgressiveBackupCommand.php`
- `SchemaMetadataCache` - `app/Support/SchemaMetadataCache.php`
- `SchemaMetadataCacheTest` - `tests/Unit/SchemaMetadataCacheTest.php`
- `UserFactory` - `database/factories/UserFactory.php`

## Command Nodes

- `database:backup-daily` - `app/Console/Commands/BackupDatabaseDailyCommand.php`
- `db:backup-progressive` - `app/Console/Commands/ProgressiveBackupCommand.php`

## Table Nodes

- `information_schema.KEY_COLUMN_USAGE`
- `information_schema.statistics`
- `performance_schema.table_io_waits_summary_by_index_usage`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| database -> core (instantiates) | 72 |
| database -> core (checks_table) | 61 |
| database -> core (writes_table) | 58 |
| database -> core (calls) | 56 |
| database -> import (checks_table) | 42 |
| database -> dashboard-pinjaman (checks_table) | 26 |
| database -> import (defines_table) | 23 |
| database -> dashboard-simpanan (checks_table) | 15 |
| database -> core (reads_table) | 15 |
| database -> dashboard-pinjaman (alters_table) | 15 |
| database -> jobs-snapshots (checks_table) | 14 |
| database -> core (accepts) | 13 |
| database -> jobs-snapshots (defines_table) | 12 |
| database -> import (uses_table) | 11 |
| database -> dashboard-pinjaman (defines_table) | 11 |
| database -> access-control (protected_by) | 11 |
| database -> core (alters_table) | 10 |
| database -> core (defines_table) | 9 |
| database -> tests (calls) | 9 |
| database -> dashboard-simpanan (alters_table) | 8 |
| database -> dashboard-simpanan (defines_table) | 7 |
| database -> dashboard-harian (checks_table) | 7 |
| database -> import (alters_table) | 7 |
| database -> jobs-snapshots (alters_table) | 6 |
| database -> core (uses_table) | 5 |
| database -> dashboard-pinjaman (uses_table) | 5 |
| database -> access-control (defines_table) | 5 |
| database -> dashboard-harian (alters_table) | 5 |
| import -> database (calls) | 5 |
| database -> tests (extends) | 5 |
