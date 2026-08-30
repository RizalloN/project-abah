# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|777| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|322| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|232| d_0d45f5fd46["core"]
    d_c1933689a3["dashboard-pinjaman"] -->|171| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|145| d_c393a69167["access-control"]
    d_59830ebc3a["tests"] -->|133| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|126| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|119| d_7c16aa6a77["jobs-snapshots"]
    d_39bcb775e4["bank-pipeline"] -->|114| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|98| d_0d45f5fd46["core"]
    d_c1933689a3["dashboard-pinjaman"] -->|76| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|66| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|66| d_c1933689a3["dashboard-pinjaman"]
    d_59830ebc3a["tests"] -->|65| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|63| d_7c16aa6a77["jobs-snapshots"]
    d_a0a49fdd98["dashboard-harian"] -->|60| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|59| d_c1933689a3["dashboard-pinjaman"]
    d_59830ebc3a["tests"] -->|58| d_d942f64886["import"]
    d_c393a69167["access-control"] -->|53| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|53| d_0d45f5fd46["core"]
    d_d942f64886["import"] -->|53| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|52| d_59830ebc3a["tests"]
    d_0d45f5fd46["core"] -->|49| d_d942f64886["import"]
    d_a0a49fdd98["dashboard-harian"] -->|45| d_c393a69167["access-control"]
    d_a0a49fdd98["dashboard-harian"] -->|39| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|36| d_a0a49fdd98["dashboard-harian"]
    d_b70a9d941d["input-management"] -->|36| d_c393a69167["access-control"]
    d_3549b0028b["database"] -->|35| d_c1933689a3["dashboard-pinjaman"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|33| d_90026ec21f["dashboard-simpanan"]
    d_0d45f5fd46["core"] -->|33| d_39bcb775e4["bank-pipeline"]
    d_c1933689a3["dashboard-pinjaman"] -->|33| d_7c16aa6a77["jobs-snapshots"]
    d_90026ec21f["dashboard-simpanan"] -->|32| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|31| d_90026ec21f["dashboard-simpanan"]
    d_1da4ffdd60["kpi"] -->|30| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|30| d_7c16aa6a77["jobs-snapshots"]
    d_7c16aa6a77["jobs-snapshots"] -->|28| d_90026ec21f["dashboard-simpanan"]
    d_b89824cc5a["almafacts"] -->|27| d_c393a69167["access-control"]
    d_a0a49fdd98["dashboard-harian"] -->|27| d_90026ec21f["dashboard-simpanan"]
    d_90026ec21f["dashboard-simpanan"] -->|25| d_7c16aa6a77["jobs-snapshots"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2695 | [open](domains/import.md) |
| core | 1668 | [open](domains/core.md) |
| jobs-snapshots | 743 | [open](domains/jobs-snapshots.md) |
| dashboard-pinjaman | 716 | [open](domains/dashboard-pinjaman.md) |
| database | 662 | [open](domains/database.md) |
| dashboard-simpanan | 529 | [open](domains/dashboard-simpanan.md) |
| dashboard-harian | 421 | [open](domains/dashboard-harian.md) |
| tests | 408 | [open](domains/tests.md) |
| bank-pipeline | 401 | [open](domains/bank-pipeline.md) |
| presentation | 285 | [open](domains/presentation.md) |
| access-control | 174 | [open](domains/access-control.md) |
| prognosa | 157 | [open](domains/prognosa.md) |
| marketshare | 127 | [open](domains/marketshare.md) |
| almafacts | 118 | [open](domains/almafacts.md) |
| knowledge-graph | 114 | [open](domains/knowledge-graph.md) |
| input-management | 50 | [open](domains/input-management.md) |
| platform | 45 | [open](domains/platform.md) |
| kpi | 31 | [open](domains/kpi.md) |
| routing | 2 | [open](domains/routing.md) |
