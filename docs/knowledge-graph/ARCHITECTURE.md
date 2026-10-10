# Architecture Map

This is a compact cross-domain view. Edge labels are static relation counts, not runtime traffic.

```mermaid
flowchart LR
    d_d942f64886["import"] -->|811| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|248| d_0d45f5fd46["core"]
    d_90026ec21f["dashboard-simpanan"] -->|215| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|207| d_c393a69167["access-control"]
    d_d942f64886["import"] -->|175| d_7c16aa6a77["jobs-snapshots"]
    d_3549b0028b["database"] -->|173| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|128| d_0d45f5fd46["core"]
    d_59830ebc3a["tests"] -->|127| d_c1933689a3["dashboard-pinjaman"]
    d_39bcb775e4["bank-pipeline"] -->|117| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|109| d_0d45f5fd46["core"]
    d_3549b0028b["database"] -->|96| d_d942f64886["import"]
    d_59830ebc3a["tests"] -->|91| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|86| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|83| d_c1933689a3["dashboard-pinjaman"]
    d_d942f64886["import"] -->|82| d_59830ebc3a["tests"]
    d_c1933689a3["dashboard-pinjaman"] -->|78| d_7c16aa6a77["jobs-snapshots"]
    d_7c16aa6a77["jobs-snapshots"] -->|76| d_d942f64886["import"]
    d_7c16aa6a77["jobs-snapshots"] -->|69| d_0d45f5fd46["core"]
    d_0d45f5fd46["core"] -->|67| d_c393a69167["access-control"]
    d_c1933689a3["dashboard-pinjaman"] -->|63| d_59830ebc3a["tests"]
    d_3549b0028b["database"] -->|58| d_c1933689a3["dashboard-pinjaman"]
    d_a0a49fdd98["dashboard-harian"] -->|56| d_c393a69167["access-control"]
    d_90026ec21f["dashboard-simpanan"] -->|54| d_c1933689a3["dashboard-pinjaman"]
    d_0d45f5fd46["core"] -->|53| d_c1933689a3["dashboard-pinjaman"]
    d_59830ebc3a["tests"] -->|50| d_7c16aa6a77["jobs-snapshots"]
    d_90026ec21f["dashboard-simpanan"] -->|48| d_0d45f5fd46["core"]
    d_7c16aa6a77["jobs-snapshots"] -->|48| d_59830ebc3a["tests"]
    d_0d45f5fd46["core"] -->|47| d_7c16aa6a77["jobs-snapshots"]
    d_0d45f5fd46["core"] -->|46| d_d942f64886["import"]
    d_d942f64886["import"] -->|45| d_90026ec21f["dashboard-simpanan"]
    d_7c16aa6a77["jobs-snapshots"] -->|43| d_90026ec21f["dashboard-simpanan"]
    d_d942f64886["import"] -->|43| d_a0a49fdd98["dashboard-harian"]
    d_0d45f5fd46["core"] -->|42| d_d294fcce0c["platform"]
    d_a0a49fdd98["dashboard-harian"] -->|41| d_c1933689a3["dashboard-pinjaman"]
    d_b70a9d941d["input-management"] -->|36| d_c393a69167["access-control"]
    d_3549b0028b["database"] -->|35| d_7c16aa6a77["jobs-snapshots"]
    d_c1933689a3["dashboard-pinjaman"] -->|35| d_d294fcce0c["platform"]
    d_90026ec21f["dashboard-simpanan"] -->|35| d_d942f64886["import"]
    d_a0a49fdd98["dashboard-harian"] -->|35| d_0d45f5fd46["core"]
    d_b89824cc5a["almafacts"] -->|34| d_c393a69167["access-control"]
```

## Domain Index

| Domain | Nodes | Detail |
| --- | ---: | --- |
| import | 2989 | [open](domains/import.md) |
| core | 1389 | [open](domains/core.md) |
| dashboard-pinjaman | 1360 | [open](domains/dashboard-pinjaman.md) |
| jobs-snapshots | 937 | [open](domains/jobs-snapshots.md) |
| dashboard-simpanan | 786 | [open](domains/dashboard-simpanan.md) |
| database | 746 | [open](domains/database.md) |
| dashboard-harian | 514 | [open](domains/dashboard-harian.md) |
| tests | 508 | [open](domains/tests.md) |
| bank-pipeline | 412 | [open](domains/bank-pipeline.md) |
| presentation | 287 | [open](domains/presentation.md) |
| access-control | 214 | [open](domains/access-control.md) |
| prognosa | 199 | [open](domains/prognosa.md) |
| marketshare | 141 | [open](domains/marketshare.md) |
| almafacts | 139 | [open](domains/almafacts.md) |
| knowledge-graph | 117 | [open](domains/knowledge-graph.md) |
| platform | 99 | [open](domains/platform.md) |
| kpi | 60 | [open](domains/kpi.md) |
| input-management | 58 | [open](domains/input-management.md) |
| routing | 2 | [open](domains/routing.md) |
