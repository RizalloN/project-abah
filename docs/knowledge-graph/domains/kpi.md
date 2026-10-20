# Domain: kpi

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=kpi --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 3 |
| method | 40 |
| class | 3 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `KpiPersonnelReferenceSyncService` | class | 24 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:11` |
| `KpiRmSmeDashboardService` | class | 18 | `app/Services/Reports/KpiRmSmeDashboardService.php:7` |
| `syncReference` | method | 17 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:53` |
| `build` | method | 12 | `app/Services/Reports/KpiRmSmeDashboardService.php:122` |
| `personnelRecords` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:147` |
| `syncMbmAssignments` | method | 11 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:345` |
| `syncMbmNames` | method | 11 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:282` |
| `cell` | method | 7 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:480` |
| `headerIndex` | method | 6 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:419` |
| `branchInfo` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:507` |
| `personFromRow` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:232` |
| `personMeta` | method | 5 | `app/Services/Reports/KpiRmSmeDashboardService.php:299` |
| `summary` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:544` |
| `sync` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:21` |
| `KpiWorkbookSnapshotService` | class | 4 | `app/Services/Reports/KpiWorkbookSnapshotService.php:8` |
| `associateRows` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:246` |
| `branchCode` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:293` |
| `cleanOrganization` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:313` |
| `hasAuthoritativePersonnelReference` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:446` |
| `identity` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:268` |
| `metricValues` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:326` |
| `normalizeBc` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:499` |
| `normalizePn` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:492` |
| `nullableCell` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:485` |
| `number` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:339` |
| `period` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:374` |
| `read` | method | 4 | `app/Services/Reports/KpiWorkbookSnapshotService.php:17` |
| `rowIsInScope` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:283` |
| `sameRole` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:441` |
| `stats` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:528` |

## Class Nodes

- `KpiPersonnelReferenceSyncService` - `app/Services/Reports/KpiPersonnelReferenceSyncService.php`
- `KpiRmSmeDashboardService` - `app/Services/Reports/KpiRmSmeDashboardService.php`
- `KpiWorkbookSnapshotService` - `app/Services/Reports/KpiWorkbookSnapshotService.php`

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
| kpi -> core (instantiates) | 1 |
