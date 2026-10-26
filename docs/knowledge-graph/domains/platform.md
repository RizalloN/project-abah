# Domain: platform

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=platform --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| command | 3 |
| file | 28 |
| method | 52 |
| unresolved_symbol | 5 |
| class | 11 |
| table | 2 |
| view | 5 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `layouts.admin` | view | 70 | `resources/views/layouts/admin.blade.php:1` |
| `layouts.sidebar` | view | 55 | `resources/views/layouts/sidebar.blade.php:1` |
| `get` | method | 29 | `app/Support/ReportCacheVersion.php:9` |
| `composite` | method | 28 | `app/Support/ReportCacheVersion.php:17` |
| `bump` | method | 16 | `app/Support/ReportCacheVersion.php:31` |
| `AppServiceProvider` | class | 15 | `app/Providers/AppServiceProvider.php:25` |
| `boot` | method | 11 | `app/Providers/AppServiceProvider.php:70` |
| `shouldFinishEarly` | method | 11 | `app/Http/Middleware/FinishResponseBeforeDeferredCallbacks.php:55` |
| `handle` | method | 10 | `app/Console/Commands/WarmDashboardCache.php:34` |
| `sendWithExplicitLength` | method | 10 | `app/Http/Middleware/FinishResponseBeforeDeferredCallbacks.php:73` |
| `FinishResponseBeforeDeferredCallbacks` | class | 9 | `app/Http/Middleware/FinishResponseBeforeDeferredCallbacks.php:21` |
| `handle` | method | 9 | `app/Http/Middleware/MonitorRequestPerformance.php:15` |
| `CacheMaintenanceService` | class | 8 | `app/Services/CacheMaintenanceService.php:9` |
| `PartitionMaintenanceService` | class | 8 | `app/Support/PartitionMaintenanceService.php:8` |
| `WarmDashboardCache` | class | 8 | `app/Console/Commands/WarmDashboardCache.php:15` |
| `securityRateLimitKey` | method | 8 | `app/Providers/AppServiceProvider.php:147` |
| `ReportCacheVersion` | class | 7 | `app/Support/ReportCacheVersion.php:7` |
| `maintain` | method | 7 | `app/Services/CacheMaintenanceService.php:16` |
| `resolveSinglePartitionForValue` | method | 7 | `app/Support/PartitionMaintenanceService.php:15` |
| `truncatePartition` | method | 7 | `app/Support/PartitionMaintenanceService.php:52` |
| `LogMaintenanceService` | class | 6 | `app/Services/LogMaintenanceService.php:9` |
| `cache` | table | 6 | - |
| `handle` | method | 6 | `app/Http/Middleware/FinishResponseBeforeDeferredCallbacks.php:27` |
| `layouts.partials.theme` | view | 6 | `resources/views/layouts/partials/theme.blade.php:1` |
| `pruneOrphanedDbSessions` | method | 6 | `app/Services/CacheMaintenanceService.php:135` |
| `refreshSourceCache` | method | 6 | `app/Services/Reports/SppgReportService.php:103` |
| `registerCustomQueueExtensions` | method | 6 | `app/Providers/AppServiceProvider.php:343` |
| `registerSecurityRateLimiters` | method | 6 | `app/Providers/AppServiceProvider.php:134` |
| `supportsPartitionDdl` | method | 6 | `app/Support/PartitionMaintenanceService.php:10` |
| `components.guest.layout` | view | 5 | - |

## Class Nodes

- `AppServiceProvider` - `app/Providers/AppServiceProvider.php`
- `CacheMaintenanceService` - `app/Services/CacheMaintenanceService.php`
- `FinishResponseBeforeDeferredCallbacks` - `app/Http/Middleware/FinishResponseBeforeDeferredCallbacks.php`
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
- `logs:maintenance` - `app/Console/Commands/MaintainApplicationLogsCommand.php`
- `report:warm-cache` - `app/Console/Commands/WarmDashboardCache.php`

## View Nodes

- `components.guest.layout`
- `layouts.admin` - `resources/views/layouts/admin.blade.php`
- `layouts.guest` - `resources/views/layouts/guest.blade.php`
- `layouts.partials.theme` - `resources/views/layouts/partials/theme.blade.php`
- `layouts.sidebar` - `resources/views/layouts/sidebar.blade.php`

## Table Nodes

- `cache`
- `cache_locks`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| platform -> core (calls) | 37 |
| core -> platform (calls) | 27 |
| dashboard-pinjaman -> platform (calls) | 16 |
| core -> platform (extends_view) | 15 |
| dashboard-pinjaman -> platform (extends_view) | 15 |
| platform -> dashboard-pinjaman (references_route) | 14 |
| platform -> dashboard-simpanan (references_route) | 12 |
| platform -> core (accepts) | 12 |
| import -> platform (extends_view) | 10 |
| dashboard-simpanan -> platform (calls) | 9 |
| jobs-snapshots -> platform (calls) | 9 |
| import -> platform (calls) | 8 |
| dashboard-simpanan -> platform (extends_view) | 8 |
| platform -> core (references_route) | 8 |
| core -> platform (contains) | 7 |
| platform -> import (references_route) | 6 |
| dashboard-harian -> platform (calls) | 5 |
| platform -> core (instantiates) | 5 |
| almafacts -> platform (extends_view) | 5 |
| access-control -> platform (uses_component) | 4 |
| platform -> dashboard-harian (references_route) | 4 |
| platform -> almafacts (references_route) | 4 |
| dashboard-harian -> platform (extends_view) | 4 |
| platform -> access-control (references_route) | 3 |
| platform -> access-control (calls) | 3 |
| platform -> jobs-snapshots (instantiates) | 3 |
| platform -> core (extends) | 3 |
| platform -> input-management (references_route) | 3 |
| database -> platform (uses_table) | 2 |
| database -> platform (checks_table) | 2 |
