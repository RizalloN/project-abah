# Database Indexing Policy - Project ABAH

## Overview
This project has undergone database optimization to improve performance and reduce storage overhead. A key part of this optimization is the removal of redundant and duplicate indexes.

## Rules for Future Changes

### 1. No Redundant Prefix Indexes
When adding a composite index (an index on multiple columns), do not add separate indexes for the prefix of those columns.
- **Example**: If an index exists on `(posisi, status, kantor_cabang, unit_kerja)`, then separate indexes on `(posisi)`, `(posisi, status)`, or `(posisi, status, kantor_cabang)` are **redundant** and should not be created.
- **Reason**: MySQL/MariaDB can use the leftmost prefix of a composite index to satisfy queries.

### 2. Consolidate Instead of Adding
Before adding a new index to support a query, check if an existing index can be expanded to cover the new columns while still supporting existing queries.

### 3. Check Before Migration
Always verify existing indexes using:
- `SHOW INDEX FROM table_name;`
- Or by checking the existing index maintenance migrations:
    - `2026_04_01_000004_secondary_ssa_cognos_reports.php`
    - `2026_04_19_070500_align_dashboard_harian_snapshot_schema.php`
    - `2026_04_21_091850_add_dormant_query_indexes.php`

## Optimized Tables Reference
The following tables have been recently optimized:
- `simpanan_multipn`
- `ssa_pinjaman`
- `ssa_simpanan`
- `dashboard_harian_snapshots`

---
*Last Updated: 2026-04-22*
