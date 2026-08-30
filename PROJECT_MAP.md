# Project ABAH Repository Map

The maintained repository knowledge graph starts at [docs/knowledge-graph/README.md](docs/knowledge-graph/README.md).

Use focused lookup before broad source reads:

```powershell
php artisan knowledge:graph --check
php artisan knowledge:graph DashboardHarianController
php artisan knowledge:graph ssa_pinjaman --depth=2 --limit=80
php artisan knowledge:graph import --domain=import
```

Rebuild after structural source changes:

```powershell
php artisan knowledge:graph --build
```

The check validates both source freshness and graph integrity. The generated graph connects files, classes, methods, routes, views, tables, commands, queues, and source-backed relations. It is an orientation and retrieval layer; current source, tests, database schema, and rendered behavior remain authoritative.
