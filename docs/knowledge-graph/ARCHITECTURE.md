# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|777| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|327| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|243| d_0d45f5fd46["core"]
    d_c1933689a3["dashboard-pinjaman"] -->|174| d_c393a69167["access-control"]
    d_59830ebc3a["tests"] -->|173| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|167| d_c393a69167["access-control"]
    d_0d45f5fd46["core"] -->|126| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|119| d_7c16aa6a77["jobs-snapshots"]
    d_39bcb775e4["bank-pipeline"] -->|114| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|112| d_0d45f5fd46["core"]
    d_c1933689a3["dashboard-pinjaman"] -->|106| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|103| d_c1933689a3["dashboard-pinjaman"]
    d_7c16aa6a77["jobs-snapshots"] -->|71| d_c1933689a3["dashboard-pinjaman"]
    d_3549b0028b["database"] -->|70| d_d942f64886["import"]
    d_0d45f5fd46["core"] -->|67| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|66| d_7c16aa6a77["jobs-snapshots"]
    d_a0a49fdd98["dashboard-harian"] -->|65| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|60| d_d942f64886["import"]
    d_d942f64886["import"] -->|58| d_c1933689a3["dashboard-pinjaman"]
    d_90026ec21f["dashboard-simpanan"] -->|57| d_0d45f5fd46["core"]
    d_c393a69167["access-control"] -->|54| d_0d45f5fd46["core"]
    d_d942f64886["import"] -->|53| d_59830ebc3a["tests"]
    d_0d45f5fd46["core"] -->|49| d_d942f64886["import"]
    d_c1933689a3["dashboard-pinjaman"] -->|46| d_7c16aa6a77["jobs-snapshots"]
    d_a0a49fdd98["dashboard-harian"] -->|45| d_c393a69167["access-control"]
    d_a0a49fdd98["dashboard-harian"] -->|42| d_c1933689a3["dashboard-pinjaman"]
    d_59830ebc3a["tests"] -->|42| d_7c16aa6a77["jobs-snapshots"]
    d_d942f64886["import"] -->|37| d_a0a49fdd98["dashboard-harian"]
    d_0d45f5fd46["core"] -->|36| d_90026ec21f["dashboard-simpanan"]
    d_b70a9d941d["input-management"] -->|36| d_c393a69167["access-control"]
    d_1da4ffdd60["kpi"] -->|36| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|35| d_c1933689a3["dashboard-pinjaman"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|33| d_39bcb775e4["bank-pipeline"]
    d_d942f64886["import"] -->|31| d_90026ec21f["dashboard-simpanan"]
    d_90026ec21f["dashboard-simpanan"] -->|30| d_c1933689a3["dashboard-pinjaman"]
    d_a0a49fdd98["dashboard-harian"] -->|30| d_90026ec21f["dashboard-simpanan"]
    d_90026ec21f["dashboard-simpanan"] -->|29| d_7c16aa6a77["jobs-snapshots"]
    d_7c16aa6a77["jobs-snapshots"] -->|28| d_90026ec21f["dashboard-simpanan"]
    d_b89824cc5a["almafacts"] -->|28| d_0d45f5fd46["core"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2711 | [open](domains/import.md) |
| core | 1893 | [open](domains/core.md) |
| dashboard-pinjaman | 812 | [open](domains/dashboard-pinjaman.md) |
| jobs-snapshots | 769 | [open](domains/jobs-snapshots.md) |
| database | 727 | [open](domains/database.md) |
| dashboard-simpanan | 597 | [open](domains/dashboard-simpanan.md) |
| tests | 519 | [open](domains/tests.md) |
| dashboard-harian | 442 | [open](domains/dashboard-harian.md) |
| bank-pipeline | 405 | [open](domains/bank-pipeline.md) |
| presentation | 285 | [open](domains/presentation.md) |
| prognosa | 185 | [open](domains/prognosa.md) |
| access-control | 182 | [open](domains/access-control.md) |
| almafacts | 131 | [open](domains/almafacts.md) |
| marketshare | 128 | [open](domains/marketshare.md) |
| knowledge-graph | 114 | [open](domains/knowledge-graph.md) |
| kpi | 59 | [open](domains/kpi.md) |
| input-management | 50 | [open](domains/input-management.md) |
| platform | 46 | [open](domains/platform.md) |
| routing | 2 | [open](domains/routing.md) |
