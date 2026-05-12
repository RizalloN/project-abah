# ADR: Snapshot Dirty Periods

## Status
Accepted

## Context
Source-table mutations can happen through import jobs, managed delete flows, Eloquent code, and direct SQL. The old trigger model deleted snapshot rows immediately and relied on import-specific rebuild paths to refill them. Non-import CRUD could therefore leave dashboards empty or stale until a manual rebuild.

## Decision
Use database triggers only to mark persistent dirty periods in `snapshot_dirty_periods`. A scheduled drain command claims dirty scopes and dispatches snapshot freshness jobs. Import paths may suppress row-level trigger work during bulk writes, but they must mark the affected period once after the bulk operation.

## Consequences
- Snapshot rows are not removed just because source rows changed.
- CRUD outside Laravel still records a rebuild signal.
- Dirty records survive process crashes and queue pauses.
- Full rebuild remains available via explicit force mode, while automatic recovery prefers incremental upsert paths.
- Generated shadow siblings use the `*_gc` suffix and remain additive until parity is proven.
