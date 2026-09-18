# Domain: input-management

Focused generated context. Query a symbol for exact neighbors: `php artisan knowledge:graph <term> --domain=input-management --depth=2`.

## Scale

| Kind | Count |
| --- | ---: |
| file | 9 |
| method | 27 |
| unresolved_symbol | 4 |
| route | 7 |
| class | 7 |
| table | 2 |
| view | 2 |

## Main Hubs

| Node | Kind | Degree | Source |
| --- | --- | ---: | --- |
| `BusinessClusterReportService` | class | 24 | `app/Services/Reports/BusinessClusterReportService.php:15` |
| `store` | method | 12 | `app/Http/Controllers/Input/BusinessClusterController.php:25` |
| `countKategoriFromCsv` | method | 11 | `app/Services/Reports/BusinessClusterReportService.php:287` |
| `bod_boc` | table | 10 | - |
| `store` | method | 10 | `app/Http/Controllers/Input/BodBocController.php:121` |
| `store` | method | 10 | `app/Http/Controllers/Input/InputRekananController.php:123` |
| `input.store` | route | 9 | - |
| `input_rekanan` | table | 9 | - |
| `report.kolaborasi.bodboc` | route | 9 | - |
| `report.kolaborasi.business-cluster` | route | 9 | - |
| `BodBoc` | class | 8 | `app/Models/BodBoc.php:10` |
| `InputRekanan` | class | 8 | `app/Models/InputRekanan.php:10` |
| `bod-boc.store` | route | 8 | - |
| `buildReport` | method | 8 | `app/Services/Reports/BusinessClusterReportService.php:27` |
| `business-cluster.store` | route | 8 | - |
| `report.kolaborasi.sppg` | route | 8 | - |
| `InputRekananController` | class | 7 | `app/Http/Controllers/Input/InputRekananController.php:13` |
| `businessCluster` | method | 7 | `app/Http/Controllers/Report/KolaborasiReportController.php:45` |
| `fetchSpreadsheet` | method | 7 | `app/Services/Reports/BusinessClusterReportService.php:215` |
| `input.index` | route | 7 | - |
| `BodBocController` | class | 6 | `app/Http/Controllers/Input/BodBocController.php:13` |
| `aggregateKategoriRows` | method | 6 | `app/Services/Reports/BusinessClusterReportService.php:126` |
| `detailKey` | method | 6 | `app/Services/Reports/BusinessClusterReportService.php:413` |
| `report.business-cluster` | view | 6 | `resources/views/report/business-cluster.blade.php:1` |
| `toCsvUrl` | method | 6 | `app/Services/Reports/BusinessClusterReportService.php:248` |
| `BusinessClusterController` | class | 5 | `app/Http/Controllers/Input/BusinessClusterController.php:14` |
| `nasabahPrioritasBodBoc` | method | 5 | `app/Http/Controllers/Report/KolaborasiReportController.php:34` |
| `normalizeHeader` | method | 5 | `app/Services/Reports/BusinessClusterReportService.php:437` |
| `readSpreadsheet` | method | 5 | `app/Services/Reports/BusinessClusterReportService.php:151` |
| `refreshSourceCaches` | method | 5 | `app/Services/Reports/BusinessClusterReportService.php:176` |

## Route Nodes

- `bod-boc.store` - `bod-boc/store`
- `business-cluster.store` - `business-cluster/store-link`
- `input.index` - `input-data`
- `input.store` - `input-data`
- `report.kolaborasi.bodboc` - `report/kolaborasi-perusahaan-anak/nasabah-prioritas-bod-boc`
- `report.kolaborasi.business-cluster` - `report/kolaborasi-perusahaan-anak/business-cluster`
- `report.kolaborasi.sppg` - `report/kolaborasi-perusahaan-anak/business-cluster/sppg`

## Class Nodes

- `BodBoc` - `app/Models/BodBoc.php`
- `BodBocController` - `app/Http/Controllers/Input/BodBocController.php`
- `BusinessClusterController` - `app/Http/Controllers/Input/BusinessClusterController.php`
- `BusinessClusterReportService` - `app/Services/Reports/BusinessClusterReportService.php`
- `BusinessClusterReportServiceTest` - `tests/Unit/BusinessClusterReportServiceTest.php`
- `InputRekanan` - `app/Models/InputRekanan.php`
- `InputRekananController` - `app/Http/Controllers/Input/InputRekananController.php`

## View Nodes

- `report.business-cluster` - `resources/views/report/business-cluster.blade.php`
- `report.nasabah-prioritas-bod-boc` - `resources/views/report/nasabah-prioritas-bod-boc.blade.php`

## Table Nodes

- `bod_boc`
- `input_rekanan`

## Cross-Domain Links

| Direction | Count |
| --- | ---: |
| input-management -> access-control (protected_by) | 35 |
| input-management -> core (calls) | 25 |
| input-management -> core (extends) | 6 |
| input-management -> core (accepts) | 5 |
| input-management -> import (references_route) | 4 |
| input-management -> import (contains) | 4 |
| input-management -> core (instantiates) | 3 |
| import -> input-management (references_route) | 3 |
| platform -> input-management (references_route) | 3 |
| database -> input-management (uses_table) | 2 |
| database -> input-management (checks_table) | 2 |
| database -> input-management (defines_table) | 2 |
| tests -> input-management (defines_table) | 2 |
| core -> input-management (contains) | 2 |
| input-management -> dashboard-simpanan (contains) | 2 |
| input-management -> presentation (uses_trait) | 2 |
| tests -> input-management (contains) | 2 |
| input-management -> platform (extends_view) | 2 |
| input-management -> core (includes_view) | 2 |
| core -> input-management (accepts) | 1 |
| core -> input-management (calls) | 1 |
| core -> input-management (injects) | 1 |
| jobs-snapshots -> input-management (accepts) | 1 |
| jobs-snapshots -> input-management (calls) | 1 |
| presentation -> input-management (injects) | 1 |
| presentation -> input-management (calls) | 1 |
| input-management -> access-control (calls) | 1 |
| input-management -> jobs-snapshots (dispatches) | 1 |
| input-management -> core (dispatches_to) | 1 |
| core -> input-management (references_route) | 1 |
