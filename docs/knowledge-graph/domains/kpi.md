# Domain: kpi

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=kpi --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 4 |
| method | 52 |
| class | 4 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `KpiPersonnelReferenceSyncService` | class | 22 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:9` |
| `KpiRmSmeDashboardService` | class | 21 | `app/Services/Reports/KpiRmSmeDashboardService.php:7` |
| `sync` | method | 17 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:19` |
| `build` | method | 12 | `app/Services/Reports/KpiRmSmeDashboardService.php:122` |
| `personnelRecords` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:117` |
| `syncMbmAssignments` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:316` |
| `syncMbmNames` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:252` |
| `KpiPersonnelReferenceSyncServiceTest` | class | 9 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:13` |
| `KpiRmSmeDashboardServiceTest` | class | 8 | `tests/Unit/KpiRmSmeDashboardServiceTest.php:8` |
| `test_failed_kpi_refresh_keeps_last_good_payload` | method | 8 | `tests/Unit/RemoteDashboardSourceTest.php:98` |
| `test_quadrant_period_uses_latest_snapshot_in_requested_month_like_kpi` | method | 8 | `tests/Unit/LandingConsumerOperationalServiceTest.php:401` |
| `cell` | method | 7 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:425` |
| `headerIndex` | method | 6 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:391` |
| `setUp` | method | 6 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:15` |
| `sources` | method | 6 | `tests/Unit/KpiRmSmeDashboardServiceTest.php:84` |
| `branchInfo` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:452` |
| `personFromRow` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:202` |
| `personMeta` | method | 5 | `app/Services/Reports/KpiRmSmeDashboardService.php:299` |
| `summary` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:489` |
| `test_default_kpi_links_do_not_overwrite_existing_custom_link` | method | 5 | `tests/Unit/LinkManagementControllerTest.php:84` |
| `test_mbm_sources_update_names_and_unit_assignments_without_fabricating_pn` | method | 5 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:162` |
| `test_rm_sme_sync_updates_existing_person_and_adds_new_person_without_deleting_other_rows` | method | 5 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:68` |
| `associateRows` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:246` |
| `branchCode` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:293` |
| `cleanOrganization` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:313` |
| `hasAuthoritativeMantriReference` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:418` |
| `identity` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:268` |
| `metricValues` | method | 4 | `app/Services/Reports/KpiRmSmeDashboardService.php:326` |
| `normalizeBc` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:444` |
| `normalizePn` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:437` |

## Class Nodes

- `KpiPersonnelReferenceSyncService` - `app/Services/Reports/KpiPersonnelReferenceSyncService.php`
- `KpiPersonnelReferenceSyncServiceTest` - `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php`
- `KpiRmSmeDashboardService` - `app/Services/Reports/KpiRmSmeDashboardService.php`
- `KpiRmSmeDashboardServiceTest` - `tests/Unit/KpiRmSmeDashboardServiceTest.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| kpi -> dashboard-pinjaman (reads_table) | 7 |
| kpi -> core (calls) | 7 |
| kpi -> dashboard-pinjaman (writes_table) | 6 |
| kpi -> core (writes_table) | 5 |
| tests -> kpi (contains) | 5 |
| kpi -> core (instantiates) | 4 |
| kpi -> platform (calls) | 3 |
| kpi -> dashboard-pinjaman (checks_table) | 3 |
| kpi -> core (reads_table) | 3 |
| kpi -> tests (calls) | 3 |
| kpi -> dashboard-pinjaman (defines_table) | 2 |
| kpi -> tests (extends) | 2 |
| kpi -> core (checks_table) | 1 |
| kpi -> bank-pipeline (references_route) | 1 |
| kpi -> core (defines_table) | 1 |
| kpi -> access-control (calls) | 1 |
| kpi -> jobs-snapshots (writes_table) | 1 |
| kpi -> almafacts (instantiates) | 1 |
| bank-pipeline -> kpi (contains) | 1 |
| kpi -> dashboard-pinjaman (contains) | 1 |
