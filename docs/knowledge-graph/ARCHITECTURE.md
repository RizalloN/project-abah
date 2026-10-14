# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|818| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|212| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|189| d_c393a69167["access-control"]
    d_3549b0028b["database"] -->|169| d_0d45f5fd46["core"]
    d_d942f64886["import"] -->|167| d_0d45f5fd46["core"]
    d_39bcb775e4["bank-pipeline"] -->|114| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|100| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|92| d_d942f64886["import"]
    d_0d45f5fd46["core"] -->|67| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|62| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|58| d_c1933689a3["dashboard-pinjaman"]
    d_a0a49fdd98["dashboard-harian"] -->|54| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|52| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|50| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|46| d_d942f64886["import"]
    d_0d45f5fd46["core"] -->|43| d_d294fcce0c["platform"]
    d_0d45f5fd46["core"] -->|43| d_7c16aa6a77["jobs-snapshots"]
    d_7c16aa6a77["jobs-snapshots"] -->|40| d_c1933689a3["dashboard-pinjaman"]
    d_90026ec21f["dashboard-simpanan"] -->|40| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|40| d_c1933689a3["dashboard-pinjaman"]
    d_7c16aa6a77["jobs-snapshots"] -->|39| d_d942f64886["import"]
    d_b70a9d941d["input-management"] -->|36| d_c393a69167["access-control"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_c1933689a3["dashboard-pinjaman"] -->|34| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|33| d_90026ec21f["dashboard-simpanan"]
    d_0d45f5fd46["core"] -->|30| d_39bcb775e4["bank-pipeline"]
    d_c1933689a3["dashboard-pinjaman"] -->|28| d_d294fcce0c["platform"]
    d_7c16aa6a77["jobs-snapshots"] -->|28| d_90026ec21f["dashboard-simpanan"]
    d_3549b0028b["database"] -->|27| d_c393a69167["access-control"]
    d_b89824cc5a["almafacts"] -->|27| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|24| d_7c16aa6a77["jobs-snapshots"]
    d_c393a69167["access-control"] -->|24| d_0d45f5fd46["core"]
    d_d942f64886["import"] -->|23| d_a0a49fdd98["dashboard-harian"]
    d_90026ec21f["dashboard-simpanan"] -->|20| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|19| d_a0a49fdd98["dashboard-harian"]
    d_d942f64886["import"] -->|19| d_d294fcce0c["platform"]
    d_d294fcce0c["platform"] -->|18| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|17| d_d294fcce0c["platform"]
    d_a0a49fdd98["dashboard-harian"] -->|16| d_d942f64886["import"]
    d_3549b0028b["database"] -->|15| d_a0a49fdd98["dashboard-harian"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2284 | [open](domains/import.md) |
| core | 1310 | [open](domains/core.md) |
| dashboard-pinjaman | 948 | [open](domains/dashboard-pinjaman.md) |
| database | 751 | [open](domains/database.md) |
| jobs-snapshots | 682 | [open](domains/jobs-snapshots.md) |
| dashboard-simpanan | 634 | [open](domains/dashboard-simpanan.md) |
| dashboard-harian | 363 | [open](domains/dashboard-harian.md) |
| bank-pipeline | 362 | [open](domains/bank-pipeline.md) |
| presentation | 246 | [open](domains/presentation.md) |
| access-control | 174 | [open](domains/access-control.md) |
| prognosa | 143 | [open](domains/prognosa.md) |
| knowledge-graph | 106 | [open](domains/knowledge-graph.md) |
| almafacts | 104 | [open](domains/almafacts.md) |
| platform | 94 | [open](domains/platform.md) |
| marketshare | 94 | [open](domains/marketshare.md) |
| input-management | 51 | [open](domains/input-management.md) |
| kpi | 42 | [open](domains/kpi.md) |
| routing | 2 | [open](domains/routing.md) |
