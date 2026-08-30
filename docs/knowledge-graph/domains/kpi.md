# Domain: kpi

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=kpi --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 2 |
| method | 27 |
| class | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `KpiPersonnelReferenceSyncService` | class | 21 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:9` |
| `sync` | method | 16 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:19` |
| `personnelRecords` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:114` |
| `syncMbmAssignments` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:313` |
| `syncMbmNames` | method | 12 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:249` |
| `KpiPersonnelReferenceSyncServiceTest` | class | 8 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:13` |
| `test_failed_kpi_refresh_keeps_last_good_payload` | method | 8 | `tests/Unit/RemoteDashboardSourceTest.php:76` |
| `cell` | method | 7 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:415` |
| `headerIndex` | method | 6 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:388` |
| `branchInfo` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:442` |
| `personFromRow` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:199` |
| `setUp` | method | 5 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:15` |
| `summary` | method | 5 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:479` |
| `test_default_kpi_links_do_not_overwrite_existing_custom_link` | method | 5 | `tests/Unit/LinkManagementControllerTest.php:78` |
| `test_mbm_sources_update_names_and_unit_assignments_without_fabricating_pn` | method | 5 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:162` |
| `test_rm_sme_sync_updates_existing_person_and_adds_new_person_without_deleting_other_rows` | method | 5 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:68` |
| `normalizeBc` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:434` |
| `normalizePn` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:427` |
| `nullableCell` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:420` |
| `sameRole` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:410` |
| `test_same_pn_from_rm_mikro_and_rm_sme_keeps_both_role_references` | method | 4 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:223` |
| `textKey` | method | 4 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:405` |
| `branchCodeFromValue` | method | 3 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:454` |
| `cleanUnitName` | method | 3 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:463` |
| `defaultOrganization` | method | 3 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:468` |
| `headerKey` | method | 3 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:400` |
| `pemasarRow` | method | 3 | `app/Services/Reports/KpiPersonnelReferenceSyncService.php:213` |
| `test_link_management_includes_rm_mikro_kpi_link` | method | 3 | `tests/Unit/LinkManagementControllerTest.php:39` |
| `tearDown` | method | 2 | `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php:59` |

## Class Nodes

- `KpiPersonnelReferenceSyncService` - `app/Services/Reports/KpiPersonnelReferenceSyncService.php`
- `KpiPersonnelReferenceSyncServiceTest` - `tests/Unit/KpiPersonnelReferenceSyncServiceTest.php`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| kpi -> core (reads_table) | 9 |
| kpi -> core (writes_table) | 9 |
| kpi -> core (calls) | 7 |
| kpi -> core (checks_table) | 4 |
| kpi -> core (defines_table) | 3 |
| kpi -> core (instantiates) | 3 |
| tests -> kpi (contains) | 3 |
| kpi -> almafacts (instantiates) | 1 |
| kpi -> tests (extends) | 1 |
