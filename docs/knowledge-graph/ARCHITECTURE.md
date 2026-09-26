# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|810| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|242| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|215| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|201| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|173| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|168| d_0d45f5fd46["core"]
    d_39bcb775e4["bank-pipeline"] -->|117| d_c393a69167["access-control"]
    d_59830ebc3a["tests"] -->|109| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|108| d_c1933689a3["dashboard-pinjaman"]
    d_c1933689a3["dashboard-pinjaman"] -->|102| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|90| d_d942f64886["import"]
    d_59830ebc3a["tests"] -->|90| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|83| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|80| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|78| d_59830ebc3a["tests"]
    d_c1933689a3["dashboard-pinjaman"] -->|71| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|67| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|62| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|60| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|58| d_c1933689a3["dashboard-pinjaman"]
    d_c1933689a3["dashboard-pinjaman"] -->|58| d_59830ebc3a["tests"]
    d_90026ec21f["dashboard-simpanan"] -->|54| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|51| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|46| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|46| d_d942f64886["import"]
    d_a0a49fdd98["dashboard-harian"] -->|46| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|44| d_90026ec21f["dashboard-simpanan"]
    d_90026ec21f["dashboard-simpanan"] -->|43| d_0d45f5fd46["core"]
    d_d942f64886["import"] -->|43| d_a0a49fdd98["dashboard-harian"]
    d_7c16aa6a77["jobs-snapshots"] -->|41| d_90026ec21f["dashboard-simpanan"]
    d_a0a49fdd98["dashboard-harian"] -->|41| d_c1933689a3["dashboard-pinjaman"]
    d_59830ebc3a["tests"] -->|41| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|39| d_d294fcce0c["platform"]
    d_b70a9d941d["input-management"] -->|36| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|36| d_59830ebc3a["tests"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_b89824cc5a["almafacts"] -->|34| d_c393a69167["access-control"]
    d_3549b0028b["database"] -->|33| d_90026ec21f["dashboard-simpanan"]
    d_a0a49fdd98["dashboard-harian"] -->|31| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|30| d_39bcb775e4["bank-pipeline"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2887 | [open](domains/import.md) |
| core | 1347 | [open](domains/core.md) |
| dashboard-pinjaman | 1275 | [open](domains/dashboard-pinjaman.md) |
| jobs-snapshots | 834 | [open](domains/jobs-snapshots.md) |
| dashboard-simpanan | 759 | [open](domains/dashboard-simpanan.md) |
| database | 737 | [open](domains/database.md) |
| dashboard-harian | 460 | [open](domains/dashboard-harian.md) |
| tests | 428 | [open](domains/tests.md) |
| bank-pipeline | 399 | [open](domains/bank-pipeline.md) |
| presentation | 278 | [open](domains/presentation.md) |
| access-control | 211 | [open](domains/access-control.md) |
| prognosa | 185 | [open](domains/prognosa.md) |
| almafacts | 139 | [open](domains/almafacts.md) |
| marketshare | 130 | [open](domains/marketshare.md) |
| knowledge-graph | 117 | [open](domains/knowledge-graph.md) |
| platform | 99 | [open](domains/platform.md) |
| kpi | 61 | [open](domains/kpi.md) |
| input-management | 58 | [open](domains/input-management.md) |
| routing | 2 | [open](domains/routing.md) |
