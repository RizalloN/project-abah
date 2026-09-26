# Knowledge Graph Architecture Reference & Project Memory

Dokumen ini adalah memori permanen dan referensi arsitektur Project ABAH yang diekstrak langsung dari Repository Knowledge Graph (10.248 nodes, 40.183 edges, 19 domains).

---

## 1. Peta 19 Domain dan Topologi Sistem

| Domain | Nodes | Fokus & Komponen Kunci |
| --- | ---: | --- |
| **`import`** | 2.801 | Engine import (`ImportExcelController`, `ImportIndexController`, `ImportFileController`, `MySqlBulkLoadService`, `ReportDataSyncService`, `Lw321PnImportStrategy`). Pipeline: validasi file -> deteksi header -> direct SQL / staged CSV bulk insert -> sinkronisasi downstream -> trigger snapshots. |
| **`core`** | 1.345 | Master metadata (`nama_report`), anggaran RKA (`rka`), target kinerja (`performance_targets`, `performance_pis_per_produk`), referensi uker (`referensi_uker`). |
| **`dashboard-pinjaman`** | 1.227 | Analitik pinjaman (`daily_loan_dinamis`, `DashboardPinjamanReportController`, `KinerjaRmReportController`, `KinerjaRmMikroReportController`, `lw325_ph`, `ssa_pinjaman`, `brihc_pemasar`). Fitur: Matrix Pergeseran Kolek, Kredit, Chart Periodik, Analisa UG-NPL, Mismatch Kolek, Tunggakan Kecil, Realisasi 6 Bulan Menunggak, Data PH. |
| **`jobs-snapshots`** | 825 | Snapshot builder & queue worker (`ReportSnapshotBuilder`, `performance_rm_snapshots`, `ManagedReportSnapshotRebuildCoordinator`, `SnapshotBatchAggregator`, `SnapshotDirtyPeriodService`). Antrean `snapshots-parallel`. |
| **`dashboard-simpanan`** | 759 | Analitik simpanan (`DashboardSimpananController`, `simpanan_multipn`, `ssa_simpanan`, `RasioCasaDebiturController`, `RekeningDormantController`). Tabungan, Giro, Deposito, CASA Ratio, Dormant, Digital Channels. |
| **`database`** | 736 | Skema database, migrasi, covering indexes, partisi, integritas data. |
| **`dashboard-harian`** | 447 | Agregasi dashboard eksekutif harian (`DashboardHarianSnapshotService`, `dashboard_harian_snapshots`). |
| **`tests`** | 413 | Test suite PHPUnit (`tests/Unit`, `tests/Feature`). Validasi snapshot, import guard, performa, dan integritas metrik. |
| **`bank-pipeline`** | 399 | Integrasi file pipeline & Drive ASIX (`drive_asix_files`, `drive_asix_folders`, editor spreadsheet OnlyOffice). |
| **`presentation`** | 278 | Generator presentasi otomatis (`GeneratePresentationPowerPointJob`, visualisasi Chart.js, materi rapat). |
| **`access-control`** | 210 | Keamanan dan otentikasi (`users`, `sessions`, `login_histories`, `UserManagementController`). Scoping data kantor: Kanwil, Kanca, Unit. |
| **`prognosa`** | 184 | Modul proyeksi keuangan dan simulasi target. |
| **`almafacts`** | 139 | Integrasi data keuangan ALMA (`ssa_almafacts`, `AlmafactsDashboardController`). |
| **`marketshare`** | 130 | Pemetaan pangsa pasar perbankan regional (`cras`). |
| **`knowledge-graph`** | 117 | Perangkat internal pemetaan kode (`KnowledgeGraphCommand`, parser AST, graf deterministik). |
| **`platform`** | 99 | Infrastruktur framework (`cache`, `cache_locks`, service providers). |
| **`kpi`** | 60 | Sinkronisasi metrik KPI personel dan rincian mantri/RM (`KpiPersonnelReferenceSyncService`). |
| **`input-management`** | 58 | Modul input manual data non-otomatis (`bod_boc`, `input_rekanan`). |
| **`routing`** | 2 | Root routing web & API. |

---

## 2. Hub-Hub Kritis (Highest Degree Symbols)

1. **`ImportExcelController` (Degree: 379)**: Controller utama pemrosesan berkas. Menangani multi-part upload, preview modal, delimiter detection, header resolution, duplicate guard, dan direct SQL loading.
2. **`DashboardSimpananController` (Degree: 360)**: Pusat agregasi portofolio dana/simpanan, produk digital, payroll, dan ringkasan eksekutif Area 6.
3. **`daily_loan_dinamis` (Degree: 299)**: Tabel data pinjaman terbesar (~16M+ baris). Menjadi fondasi seluruh metrik pinjaman, risiko kredit, NPL, LAR, kolek mismatch, dan ranking RM.
4. **`DashboardHarianSnapshotService` (Degree: 258)**: Penggabung data multi-sumber (pinjaman + simpanan + RKA + recovery) menjadi snapshot harian cepat.
5. **`DashboardPinjamanReportController` (Degree: 194)**: Controller laporan analitik pinjaman. Bertanggung jawab atas Matrix Pergeseran Kolek, Analisa UG-NPL, Mismatch, Tunggakan Kecil, dan Rekonstruksi Portofolio.
6. **`nama_report` (Degree: 188)**: Registry metadata laporan, tipe parser, konfigurasi periode manual, dan controller penanggung jawab.
7. **`import_jobs` (Degree: 164)**: Tabel status eksekusi import (queued, processing, completed, failed) beserta context payload JSON.
8. **`ReportSnapshotBuilder` (Degree: 145)**: Pembangun snapshot periodik materialistis untuk mempercepat respon dashboard UI.

---

## 3. Aturan & Invarian Operasional Wajib

### A. Matrix Pergeseran Kolek (`matrix-pergeseran-kolek`)
- **Formula Sel Matrix**:
  - Rekening Transisi (`before_bucket != 'New Account'`): Dihitung dari saldo periode pembanding (`prev.balance_cents` / 100).
  - Rekening Baru (`before_bucket == 'New Account'`): Dihitung dari saldo periode berjalan (`curr.balance_cents` / 100).
- **Drilldown Nominatif**:
  - Popup modal wajib menyajikan:
    1. `pivot_previous_balance` (*Baki Debet Pembanding* - basis angka sel).
    2. `baki_debet1` (*Baki Debet Posisi* - saldo berjalan, dengan fallback ke saldo pembanding jika rekening aktif namun data berjalan belum termaterialisasi).
  - Kolom rupiah wajib diformat desimal standar via `formatNumber()` dan rata kanan (`text-right`).

### B. Pipeline Import & Aliasing Kolom
- Berkas ekspor core banking sering kali memiliki header singkatan atau variasi nama (`CBAL_Base`, `ORGAMT_Base`, `KOLEK_*`, `PN_REFERAL`).
- Saat menambah atau mengubah parser laporan:
  1. Daftarkan alias di `HEADER_ALIASES` pada strategi import (`*ImportStrategy.php`).
  2. Daftarkan alias di `$aliasMap` pada `ImportExcelController::getHeaderDatabaseCandidates()`.
  3. Pastikan `SmartContentHeaderGuardService::isKnownAliasOf()` mengenali kolom tersebut agar tidak ditolak oleh validasi header guard.

### C. Kinerja Database & MySQL Indexing
- **Indeks**: DILARANG menambahkan indeks duplikat atau redundant left-prefix pada tabel besar (`daily_loan_dinamis`, `simpanan_multipn`).
- **SARGability**: Jangan gunakan fungsi SQL pada kolom berindeks di klausa `WHERE` atau `JOIN` (misal gunakan `periode = '2026-09-21'`, bukan `DATE(periode) = ...`).
- **Session Locking**: Controller yang menjalankan query analitik berat WAJIB memanggil `$this->releaseSessionLockIfNeeded()` segera setelah memproses request parameter untuk membebaskan session storage bagi request browser lainnya.

### D. Snapshot & Queues
- Perubahan pada data sumber mentah tidak langsung menghitung ulang semua periode.
- Gunakan `snapshot_dirty_periods` untuk menandai periode yang terdampak.
- Antrean `snapshots-parallel` menangani kalkulasi berat di latar belakang tanpa menghambat UI.
