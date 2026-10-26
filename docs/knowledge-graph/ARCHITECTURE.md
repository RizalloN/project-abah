# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|1071| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|251| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|250| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|217| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|173| d_0d45f5fd46["core"]
    d_39bcb775e4["bank-pipeline"] -->|136| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|113| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|106| d_d942f64886["import"]
    d_0d45f5fd46["core"] -->|87| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|74| d_0d45f5fd46["core"]
    d_a0a49fdd98["dashboard-harian"] -->|66| d_c393a69167["access-control"]
    d_0d45f5fd46["core"] -->|65| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|62| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|61| d_c1933689a3["dashboard-pinjaman"]
    d_3549b0028b["database"] -->|58| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|58| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|52| d_d294fcce0c["platform"]
    d_7c16aa6a77["jobs-snapshots"] -->|43| d_d942f64886["import"]
    d_b70a9d941d["input-management"] -->|43| d_c393a69167["access-control"]
    d_7c16aa6a77["jobs-snapshots"] -->|41| d_c1933689a3["dashboard-pinjaman"]
    d_90026ec21f["dashboard-simpanan"] -->|40| d_c1933689a3["dashboard-pinjaman"]
    d_90026ec21f["dashboard-simpanan"] -->|39| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_c1933689a3["dashboard-pinjaman"] -->|34| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|33| d_90026ec21f["dashboard-simpanan"]
    d_b89824cc5a["almafacts"] -->|33| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|31| d_d294fcce0c["platform"]
    d_3549b0028b["database"] -->|30| d_c393a69167["access-control"]
    d_0d45f5fd46["core"] -->|30| d_39bcb775e4["bank-pipeline"]
    d_7c16aa6a77["jobs-snapshots"] -->|28| d_90026ec21f["dashboard-simpanan"]
    d_c393a69167["access-control"] -->|26| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|24| d_7c16aa6a77["jobs-snapshots"]
    d_d942f64886["import"] -->|23| d_a0a49fdd98["dashboard-harian"]
    d_7c16aa6a77["jobs-snapshots"] -->|22| d_a0a49fdd98["dashboard-harian"]
    d_d294fcce0c["platform"] -->|21| d_0d45f5fd46["core"]
    d_7c16aa6a77["jobs-snapshots"] -->|21| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|20| d_d942f64886["import"]
    d_d942f64886["import"] -->|19| d_d294fcce0c["platform"]
    d_bfac314fef["presentation"] -->|18| d_90026ec21f["dashboard-simpanan"]
    d_90026ec21f["dashboard-simpanan"] -->|17| d_d294fcce0c["platform"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2629 | [open](domains/import.md) |
| core | 1538 | [open](domains/core.md) |
| dashboard-pinjaman | 1077 | [open](domains/dashboard-pinjaman.md) |
| database | 791 | [open](domains/database.md) |
| jobs-snapshots | 717 | [open](domains/jobs-snapshots.md) |
| dashboard-simpanan | 638 | [open](domains/dashboard-simpanan.md) |
| dashboard-harian | 367 | [open](domains/dashboard-harian.md) |
| bank-pipeline | 363 | [open](domains/bank-pipeline.md) |
| presentation | 248 | [open](domains/presentation.md) |
| access-control | 200 | [open](domains/access-control.md) |
| prognosa | 143 | [open](domains/prognosa.md) |
| platform | 106 | [open](domains/platform.md) |
| knowledge-graph | 106 | [open](domains/knowledge-graph.md) |
| almafacts | 106 | [open](domains/almafacts.md) |
| marketshare | 94 | [open](domains/marketshare.md) |
| input-management | 51 | [open](domains/input-management.md) |
| kpi | 46 | [open](domains/kpi.md) |
| routing | 2 | [open](domains/routing.md) |
