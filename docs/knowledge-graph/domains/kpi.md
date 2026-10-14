# Domain: kpi

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=kpi --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 2 |
| method | 38 |
| class | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `KpiPersonnelReferenceSyncService` | class | 24 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:9` |
| `KpiRmSmeDashboardService` | class | 18 | `app/Services/Reports/KpiRmSmeDashboardService.php:7` |
| `syncReference` | method | 17 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:51` |
| `build` | method | 12 | `app/Services/Reports/KpiRmSmeDashboardService.php:122` |
| `personnelRecords` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:145` |
| `syncMbmAssignments` | method | 11 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:343` |
| `syncMbmNames` | method | 11 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:280` |
| `cell` | method | 7 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:474` |
| `headerIndex` | method | 6 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:417` |
| `branchInfo` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:501` |
| `personFromRow` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:230` |
| `personMeta` | method | 5 | `app/Services/Reports/KpiRmSmeDashboardService.php:299` |
| `summary` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:538` |
| `sync` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:19` |
| `associateRows` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:246` |
| `branchCode` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:293` |
| `cleanOrganization` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:313` |
| `hasAuthoritativePersonnelReference` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:444` |
| `identity` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:268` |
| `metricValues` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:326` |
| `normalizeBc` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:493` |
| `normalizePn` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:486` |
| `nullableCell` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:479` |
| `number` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:339` |
| `period` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:374` |
| `rowIsInScope` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:283` |
| `sameRole` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:439` |
| `stats` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:528` |
| `summary` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:416` |
| `textKey` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:434` |

## Class Nodes

- `KpiPersonnelReferenceSyncService` - `app/Services/Reports/KpiPersonnelReferenceSyncService.php`
- `KpiRmSmeDashboardService` - `app/Services/Reports/KpiRmSmeDashboardService.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| kpi -> dashboard-pinjaman (reads_table) | 4 |
| kpi -> dashboard-pinjaman (checks_table) | 3 |
| kpi -> dashboard-pinjaman (writes_table) | 3 |
| kpi -> platform (calls) | 1 |
| kpi -> core (checks_table) | 1 |
| kpi -> core (reads_table) | 1 |
| kpi -> core (writes_table) | 1 |
| kpi -> core (calls) | 1 |
