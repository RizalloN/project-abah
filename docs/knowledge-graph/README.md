# Project ABAH Knowledge Graph

Generated, deterministic repository map for coding assistants and maintainers. It connects files, PHP symbols, methods, routes, views, database tables, commands, queues, and their source-backed relations.

## Fast Start

1. Read this file, then open only the relevant domain file under `domains/`.
2. Query a focused neighborhood instead of reading the whole graph:

```powershell
php artisan knowledge:graph DashboardHarianController
php artisan knowledge:graph ssa_pinjaman --depth=2 --limit=80
php artisan knowledge:graph import --domain=import --limit=60
```

3. Before relying on generated knowledge, verify freshness and structural integrity:

```powershell
php artisan knowledge:graph --check
```

If stale, rebuild with `php artisan knowledge:graph --build`.

## Repository Scale

| Metric | Count |
| --- | ---: |
| Source files | 670 |
| Graph nodes | 8,500 |
| Graph edges | 34,076 |
| Domains | 18 |

## Read The Right Artifact

| Need | Open |
| --- | --- |
| System-level mental model | [ARCHITECTURE.md](ARCHITECTURE.md) |
| HTTP entry points | [ROUTES.md](ROUTES.md) |
| Tables and data consumers | [DATA.md](DATA.md) |
| One bounded business area | `domains/<domain>.md` |
| Exact machine lookup | `nodes.jsonl` and `edges.jsonl` |
| Counts and freshness hash | `manifest.json` |

## Highest Connectivity Symbols

| Node | Kind | Domain | Degree |
| --- | --- | --- | ---: |
| `ImportExcelController` | class | import | 384 |
| `DashboardSimpananController` | class | dashboard-simpanan | 343 |
| `DashboardHarianSnapshotService` | class | dashboard-harian | 207 |
| `DashboardPinjamanReportController` | class | dashboard-pinjaman | 161 |
| `ImportIndexController` | class | import | 158 |
| `nama_report` | table | core | 145 |
| `ReportSnapshotBuilder` | class | jobs-snapshots | 128 |
| `ImportFileController` | class | import | 125 |
| `import_jobs` | table | import | 113 |
| `KinerjaRmReportController` | class | dashboard-pinjaman | 104 |
| `ImportProgressService` | class | import | 102 |
| `daily_loan_dinamis` | table | dashboard-pinjaman | 99 |
| `ImportReportPhController` | class | import | 95 |
| `AlmafactsDashboardController` | class | almafacts | 89 |
| `KinerjaRmMikroReportController` | class | dashboard-pinjaman | 87 |
| `LandingMicroPerformanceService` | class | core | 84 |
| `ImportPerformancePisPerProdukController` | class | import | 83 |
| `DriveAsixFile` | class | bank-pipeline | 80 |
| `NativeOpenXmlPowerPointRenderer` | class | presentation | 71 |
| `ReportDataSyncService` | class | core | 71 |

## Edge Semantics

`dispatches_to` connects routes to controller methods; `calls` connects methods; `injects` and `accepts` show typed dependencies; `renders`, `includes_view`, and `references_route` connect UI flow; table relations identify reads, writes, schema definitions, and model mappings; `dispatches` and `uses_queue` expose asynchronous flow.

Every relation with source evidence includes a repository path and line. The graph is navigation evidence, not a replacement for validating business rules or runtime data.
