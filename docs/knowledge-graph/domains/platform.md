# Domain: platform

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=platform --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 4 |
| file | 32 |
| method | 41 |
| unresolved_symbol | 5 |
| class | 10 |
| table | 2 |
| view | 4 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `layouts.admin` | view | 64 | `resources/views/layouts/admin.blade.php:1` |
| `layouts.sidebar` | view | 53 | `resources/views/layouts/sidebar.blade.php:1` |
| `get` | method | 22 | `app/Support/ReportCacheVersion.php:9` |
| `bump` | method | 19 | `app/Support/ReportCacheVersion.php:31` |
| `composite` | method | 19 | `app/Support/ReportCacheVersion.php:17` |
| `AppServiceProvider` | class | 14 | `app/Providers/AppServiceProvider.php:25` |
| `boot` | method | 10 | `app/Providers/AppServiceProvider.php:68` |
| `handle` | method | 9 | `app/Http/Middleware/MonitorRequestPerformance.php:15` |
| `CacheMaintenanceService` | class | 8 | `app/Services/CacheMaintenanceService.php:9` |
| `PartitionMaintenanceService` | class | 8 | `app/Support/PartitionMaintenanceService.php:8` |
| `WarmDashboardCache` | class | 8 | `app/Console/Commands/WarmDashboardCache.php:14` |
| `securityRateLimitKey` | method | 8 | `app/Providers/AppServiceProvider.php:142` |
| `ReportCacheVersion` | class | 7 | `app/Support/ReportCacheVersion.php:7` |
| `handle` | method | 7 | `app/Console/Commands/WarmDashboardCache.php:33` |
| `maintain` | method | 7 | `app/Services/CacheMaintenanceService.php:16` |
| `resolveSinglePartitionForValue` | method | 7 | `app/Support/PartitionMaintenanceService.php:15` |
| `truncatePartition` | method | 7 | `app/Support/PartitionMaintenanceService.php:52` |
| `LogMaintenanceService` | class | 6 | `app/Services/LogMaintenanceService.php:9` |
| `cache` | table | 6 | - |
| `pruneOrphanedDbSessions` | method | 6 | `app/Services/CacheMaintenanceService.php:135` |
| `refreshSourceCache` | method | 6 | `app/Services/Reports/SppgReportService.php:103` |
| `registerCustomQueueExtensions` | method | 6 | `app/Providers/AppServiceProvider.php:270` |
| `registerQueueWorkerAutoEnsure` | method | 6 | `app/Providers/AppServiceProvider.php:151` |
| `registerSecurityRateLimiters` | method | 6 | `app/Providers/AppServiceProvider.php:129` |
| `supportsPartitionDdl` | method | 6 | `app/Support/PartitionMaintenanceService.php:10` |
| `components.guest.layout` | view | 5 | - |
| `handle` | method | 5 | `app/Console/Commands/MaintainApplicationCacheCommand.php:14` |
| `invalidateReportCaches` | method | 5 | `app/Support/ReportDataSyncService.php:1477` |
| `key` | method | 5 | `app/Support/ReportCacheVersion.php:40` |
| `maintain` | method | 5 | `app/Services/LogMaintenanceService.php:17` |

## Class Nodes

- `AppServiceProvider` - `app/Providers/AppServiceProvider.php`
- `CacheMaintenanceService` - `app/Services/CacheMaintenanceService.php`
- `GuestLayout` - `app/View/Components/GuestLayout.php`
- `JobHealthSweepMiddleware` - `app/Http/Middleware/JobHealthSweepMiddleware.php`
- `LogMaintenanceService` - `app/Services/LogMaintenanceService.php`
- `MaintainApplicationCacheCommand` - `app/Console/Commands/MaintainApplicationCacheCommand.php`
- `MonitorRequestPerformance` - `app/Http/Middleware/MonitorRequestPerformance.php`
- `PartitionMaintenanceService` - `app/Support/PartitionMaintenanceService.php`
- `ReportCacheVersion` - `app/Support/ReportCacheVersion.php`
- `WarmDashboardCache` - `app/Console/Commands/WarmDashboardCache.php`

## Command Nodes

- `cache:maintenance` - `app/Console/Commands/MaintainApplicationCacheCommand.php`
- `config:clear`
- `logs:maintenance` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `report:warm-cache` - `app/Console/Commands/WarmDashboardCache.php`

## View Nodes

- `components.guest.layout`
- `layouts.admin` - `resources/views/layouts/admin.blade.php`
- `layouts.guest` - `resources/views/layouts/guest.blade.php`
- `layouts.sidebar` - `resources/views/layouts/sidebar.blade.php`

## Table Nodes

- `cache`
- `cache_locks`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| platform -> core (calls) | 26 |
| core -> platform (calls) | 18 |
| dashboard-pinjaman -> platform (calls) | 14 |
| core -> platform (extends_view) | 14 |
| platform -> dashboard-pinjaman (references_route) | 13 |
| dashboard-pinjaman -> platform (extends_view) | 13 |
| platform -> dashboard-simpanan (references_route) | 12 |
| import -> platform (extends_view) | 10 |
| dashboard-simpanan -> platform (calls) | 8 |
| import -> platform (calls) | 8 |
| dashboard-simpanan -> platform (extends_view) | 8 |
| platform -> core (references_route) | 7 |
| dashboard-harian -> platform (calls) | 6 |
| platform -> import (references_route) | 6 |
| platform -> core (accepts) | 5 |
| jobs-snapshots -> platform (calls) | 5 |
| platform -> core (instantiates) | 5 |
| core -> platform (contains) | 5 |
| almafacts -> platform (extends_view) | 5 |
| access-control -> platform (uses_component) | 4 |
| platform -> dashboard-harian (references_route) | 4 |
| platform -> almafacts (references_route) | 4 |
| dashboard-harian -> platform (extends_view) | 4 |
| platform -> access-control (references_route) | 3 |
| platform -> jobs-snapshots (instantiates) | 3 |
| kpi -> platform (calls) | 3 |
| platform -> core (extends) | 3 |
| platform -> input-management (references_route) | 3 |
| database -> platform (uses_table) | 2 |
| platform -> core (invokes_command) | 2 |
