# Domain: platform

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=platform --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 23 |
| method | 18 |
| class | 4 |
| unresolved_symbol | 1 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `AppServiceProvider` | class | 14 | `app/Providers/AppServiceProvider.php:25` |
| `boot` | method | 10 | `app/Providers/AppServiceProvider.php:68` |
| `handle` | method | 9 | `app/Http/Middleware/MonitorRequestPerformance.php:15` |
| `securityRateLimitKey` | method | 8 | `app/Providers/AppServiceProvider.php:142` |
| `handle` | method | 7 | `app/Http/Middleware/ReleaseSessionLockMiddleware.php:12` |
| `registerCustomQueueExtensions` | method | 6 | `app/Providers/AppServiceProvider.php:270` |
| `registerQueueWorkerAutoEnsure` | method | 6 | `app/Providers/AppServiceProvider.php:151` |
| `registerSecurityRateLimiters` | method | 6 | `app/Providers/AppServiceProvider.php:129` |
| `queueWorkerPoolFor` | method | 5 | `app/Providers/AppServiceProvider.php:238` |
| `registerQueueWorkerHeartbeats` | method | 5 | `app/Providers/AppServiceProvider.php:184` |
| `handle` | method | 4 | `app/Http/Middleware/JobHealthSweepMiddleware.php:13` |
| `middleware` | method | 4 | `app/Jobs/RebuildChartPeriodikPeriodJob.php:33` |
| `middleware` | method | 4 | `app/Jobs/RebuildDashboardPeriodJob.php:34` |
| `middleware` | method | 4 | `app/Jobs/RebuildDormantPeriodJob.php:34` |
| `middleware` | method | 4 | `app/Jobs/RebuildHarianPeriodJob.php:34` |
| `middleware` | method | 4 | `app/Jobs/RebuildRasioPeriodJob.php:34` |
| `register` | method | 4 | `app/Providers/AppServiceProvider.php:30` |
| `registerSchemaMetadataCache` | method | 4 | `app/Providers/AppServiceProvider.php:49` |
| `JobHealthSweepMiddleware` | class | 3 | `app/Http/Middleware/JobHealthSweepMiddleware.php:11` |
| `MonitorRequestPerformance` | class | 3 | `app/Http/Middleware/MonitorRequestPerformance.php:13` |
| `ReleaseSessionLockMiddleware` | class | 3 | `app/Http/Middleware/ReleaseSessionLockMiddleware.php:10` |
| `normalizeQueueNames` | method | 3 | `app/Providers/AppServiceProvider.php:259` |

## Class Nodes

- `AppServiceProvider` - `app/Providers/AppServiceProvider.php`
- `JobHealthSweepMiddleware` - `app/Http/Middleware/JobHealthSweepMiddleware.php`
- `MonitorRequestPerformance` - `app/Http/Middleware/MonitorRequestPerformance.php`
- `ReleaseSessionLockMiddleware` - `app/Http/Middleware/ReleaseSessionLockMiddleware.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| platform -> core (calls) | 20 |
| platform -> jobs-snapshots (instantiates) | 8 |
| platform -> core (accepts) | 7 |
| platform -> import (instantiates) | 5 |
| core -> platform (contains) | 5 |
| platform -> dashboard-simpanan (references_route) | 1 |
| platform -> access-control (references_route) | 1 |
| platform -> core (renders) | 1 |
| platform -> core (instantiates) | 1 |
| platform -> access-control (writes_table) | 1 |
| platform -> database (instantiates) | 1 |
| jobs-snapshots -> platform (instantiates) | 1 |
