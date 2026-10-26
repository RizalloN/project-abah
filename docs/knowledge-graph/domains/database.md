# Domain: database

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=database --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 4 |
| file | 163 |
| function | 388 |
| method | 193 |
| unresolved_symbol | 18 |
| route | 2 |
| class | 19 |
| table | 6 |
| view | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `DailyDatabaseBackupService` | class | 46 | `app/Services/DailyDatabaseBackupService.php:15` |
| `ManagedReportBackupRecoveryService` | class | 38 | `app/Services/ManagedReportBackupRecoveryService.php:15` |
| `backup` | method | 33 | `app/Services/DailyDatabaseBackupService.php:54` |
| `up` | function | 28 | `database/migrations/2026_04_01_000003_main_report_data.php:9` |
| `DatabaseBackupService` | class | 26 | `app/Services/DatabaseBackupService.php:11` |
| `up` | function | 24 | `database/migrations/2026_04_01_000001_initial_core_tables.php:10` |
| `ProgressiveBackupCommand` | class | 23 | `app/Console/Commands/ProgressiveBackupCommand.php:12` |
| `information_schema.statistics` | table | 22 | - |
| `createChunkedBackup` | method | 20 | `app/Services/DailyDatabaseBackupService.php:277` |
| `up` | function | 18 | `database/migrations/2026_04_01_000005_dashboard_snapshots_and_triggers.php:10` |
| `CachedMySqlSchemaBuilder` | class | 17 | `app/Support/CachedMySqlSchemaBuilder.php:9` |
| `SchemaMetadataCache` | class | 16 | `app/Support/SchemaMetadataCache.php:9` |
| `invalidate` | method | 15 | `app/Support/SchemaMetadataCache.php:68` |
| `extractTableSqlFromBackup` | method | 14 | `app/Services/ManagedReportBackupRecoveryService.php:316` |
| `inspectManagedBackupDirectory` | method | 14 | `app/Services/DailyDatabaseBackupService.php:1089` |
| `handle` | method | 13 | `app/Console/Commands/RestoreDatabaseBackupCommand.php:29` |
| `performOptimizedBackup` | method | 13 | `app/Console/Commands/ProgressiveBackupCommand.php:107` |
| `write` | method | 13 | `app/Services/DatabaseBackup/ChunkedDatabaseBackupWriter.php:26` |
| `ChunkedBackupPlanner` | class | 12 | `app/Services/DatabaseBackup/ChunkedBackupPlanner.php:14` |
| `ChunkedDatabaseBackupWriter` | class | 12 | `app/Services/DatabaseBackup/ChunkedDatabaseBackupWriter.php:14` |
| `handle` | method | 12 | `app/Console/Commands/ProgressiveBackupCommand.php:17` |
| `recoverReportTable` | method | 12 | `app/Services/ManagedReportBackupRecoveryService.php:29` |
| `up` | function | 12 | `database/migrations/2026_04_01_000004_secondary_ssa_cognos_reports.php:10` |
| `CompressedDatabaseDumpRunner` | class | 10 | `app/Services/DatabaseBackup/CompressedDatabaseDumpRunner.php:9` |
| `file-management.database-backup` | route | 10 | - |
| `handle` | method | 10 | `app/Console/Commands/BackupDatabasePartCommand.php:23` |
| `put` | method | 10 | `app/Support/DatabaseBackupStatusStore.php:16` |
| `up` | function | 10 | `database/migrations/2026_04_27_optimize_import_indexes.php:20` |
| `emitProgress` | method | 9 | `app/Services/ManagedReportBackupRecoveryService.php:1191` |
| `file-management.database-backup.status` | route | 9 | - |

## Route Nodes

- `file-management.database-backup` - `file-management/database-backup`
- `file-management.database-backup.status` - `file-management/database-backup/{backupId}/status`

## Class Nodes

- `BackupDatabaseDailyCommand` - `app/Console/Commands/BackupDatabaseDailyCommand.php`
- `BackupDatabasePartCommand` - `app/Console/Commands/BackupDatabasePartCommand.php`
- `CachedMySqlSchemaBuilder` - `app/Support/CachedMySqlSchemaBuilder.php`
- `ChunkedBackupPlanner` - `app/Services/DatabaseBackup/ChunkedBackupPlanner.php`
- `ChunkedDatabaseBackupWriter` - `app/Services/DatabaseBackup/ChunkedDatabaseBackupWriter.php`
- `CompressedDatabaseDumpRunner` - `app/Services/DatabaseBackup/CompressedDatabaseDumpRunner.php`
- `DailyDatabaseBackupService` - `app/Services/DailyDatabaseBackupService.php`
- `DatabaseBackupAlreadyRunningException` - `app/Exceptions/DatabaseBackupAlreadyRunningException.php`
- `DatabaseBackupService` - `app/Services/DatabaseBackupService.php`
- `DatabaseBackupStatusStore` - `app/Support/DatabaseBackupStatusStore.php`
- `DatabaseSeeder` - `database/seeders/DatabaseSeeder.php`
- `GzipSqlVerifier` - `app/Services/DatabaseBackup/GzipSqlVerifier.php`
- `ManagedReportBackupRecoveryService` - `app/Services/ManagedReportBackupRecoveryService.php`
- `ParallelPartRunner` - `app/Services/DatabaseBackup/ParallelPartRunner.php`
- `ProgressiveBackupCommand` - `app/Console/Commands/ProgressiveBackupCommand.php`
- `RestoreDatabaseBackupCommand` - `app/Console/Commands/RestoreDatabaseBackupCommand.php`
- `SchemaDriftPreviewComposer` - `app/View/Composers/SchemaDriftPreviewComposer.php`
- `SchemaMetadataCache` - `app/Support/SchemaMetadataCache.php`
- `SuperAdminSeeder` - `database/seeders/SuperAdminSeeder.php`

## Command Nodes

- `database:backup-daily` - `app/Console/Commands/BackupDatabaseDailyCommand.php`
- `database:backup-part` - `app/Console/Commands/BackupDatabasePartCommand.php`
- `database:restore-daily` - `app/Console/Commands/RestoreDatabaseBackupCommand.php`
- `db:backup-progressive` - `app/Console/Commands/ProgressiveBackupCommand.php`

## View Nodes

- `layouts.partials.schema-drift-bell` - `resources/views/layouts/partials/schema-drift-bell.blade.php`

## Table Nodes

- `information_schema.COLUMNS`
- `information_schema.KEY_COLUMN_USAGE`
- `information_schema.TABLES`
- `information_schema.TRIGGERS`
- `information_schema.statistics`
- `performance_schema.table_io_waits_summary_by_index_usage`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| database -> core (instantiates) | 79 |
| database -> core (writes_table) | 62 |
| database -> core (checks_table) | 62 |
| database -> import (checks_table) | 47 |
| database -> core (calls) | 36 |
| database -> import (defines_table) | 28 |
| database -> dashboard-pinjaman (checks_table) | 26 |
| database -> core (accepts) | 22 |
| database -> core (reads_table) | 19 |
| database -> dashboard-pinjaman (alters_table) | 17 |
| database -> dashboard-simpanan (checks_table) | 15 |
| database -> jobs-snapshots (checks_table) | 14 |
| database -> access-control (protected_by) | 13 |
| database -> jobs-snapshots (defines_table) | 12 |
| database -> import (uses_table) | 11 |
| database -> dashboard-pinjaman (defines_table) | 11 |
| database -> core (alters_table) | 10 |
| database -> import (alters_table) | 10 |
| database -> core (defines_table) | 9 |
| database -> dashboard-simpanan (alters_table) | 8 |
| database -> dashboard-simpanan (defines_table) | 7 |
| database -> dashboard-harian (checks_table) | 7 |
| import -> database (calls) | 7 |
| database -> core (extends) | 7 |
| database -> jobs-snapshots (alters_table) | 6 |
| database -> core (uses_table) | 5 |
| database -> dashboard-pinjaman (uses_table) | 5 |
| database -> access-control (defines_table) | 5 |
| database -> dashboard-harian (alters_table) | 5 |
| database -> access-control (checks_table) | 4 |
