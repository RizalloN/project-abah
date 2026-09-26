# Project Notes

- Before adding or changing MySQL indexes, inspect existing indexes for exact duplicates and left-prefix coverage. Do not add redundant indexes, especially on large `project_abah` tables such as `simpanan_multipn`, because the database has already been optimized and duplicate indexes significantly inflate storage and import cost.

## Repository Knowledge Graph & Core Architecture Memory

- **Navigasi Utama**: Mulai investigasi di `PROJECT_MAP.md` dan `docs/knowledge-graph/README.md`.
  - Cek integritas graf: `php artisan knowledge:graph --check`. Rebuild jika stale: `php artisan knowledge:graph --build`.
  - Query lingkungan terkecil: `php artisan knowledge:graph <symbol|path|route|view|table|command> --depth=1 --limit=60`.
  - Gunakan `--domain=<name>` untuk membatasi ruang lingkup (19 domain terdaftar di `docs/knowledge-graph/domains/`).
  - Graph adalah bukti navigasi; selalu validasi ke kode sumber, schema database, dan pengujian sebelum mengubah logika.
- **Domain Invariants & Mental Model**:
  1. **`import`** (`ImportExcelController`, `ImportFileController`, `MySqlBulkLoadService`, `ReportDataSyncService`):
     - Pipeline: Upload -> Preview -> Direct SQL / Staged CSV Bulk Load -> Downstream Materialization -> Trigger Snapshots.
     - Header Mapping: Wajib melewati `SmartContentHeaderGuardService` dan alias mapping pada strategi import (`Lw321PnImportStrategy`, dll.). Header core banking (`CBAL_Base`, `ORGAMT_Base`, `KOLEK_*`, `PN_REFERAL`) harus terpetakan ke skema database (`balance_dalam_idr`, `plafon_dalam_idr`, dsb.).
  2. **`dashboard-pinjaman`** (`daily_loan_dinamis`, `DashboardPinjamanReportController`, `KinerjaRmReportController`, `lw325_ph`):
     - `daily_loan_dinamis` adalah tabel transaksi harian pinjaman utama (~16M+ baris, grain: `periode` + `nomor_rekening1`).
     - Matrix Pergeseran Kolek (`matrix-pergeseran-kolek`): Nilai sel pergeseran dihitung dari saldo periode pembanding (`prev.balance_cents` / `pivot_previous_balance`) untuk sel transisi, dan saldo berjalan (`curr.balance_cents` / `baki_debet1`) untuk New Account. Drilldown modal nominatif WAJIB menyediakan kedua saldo (`pivot_previous_balance` dan `baki_debet1`).
     - Sub-fitur analitik: `kredit` (eksposur/kualitas), `chart-periodik` (tren snapshot), `analisa-ug-npl` (downgrade), `mismatch` (kolek vs umur tunggakan), `tunggakan-kecil`, `realisasi-6-bulan-menunggak`, `data-ph` (recovery).
  3. **`dashboard-simpanan`** (`DashboardSimpananController`, `simpanan_multipn`, `ssa_simpanan`):
     - `simpanan_multipn` adalah tabel raksasa teroptimasi. Indeks baru harus dicek left-prefix redundancy agar tidak membengkakkan storage.
     - Mengelola CASA ratio, rekening dormant, dan adopsi digital (QRIS, EDC, BRIMo, BRILink, Qlola).
  4. **`jobs-snapshots`** (`ReportSnapshotBuilder`, `performance_rm_snapshots`, `SnapshotDirtyPeriodService`):
     - Pipeline snapshot asinkron via antrean `snapshots-parallel`.
     - Rebuild menggunakan deteksi `snapshot_dirty_periods` dan `snapshot_source_signatures` untuk meminimalkan re-komputasi.
  5. **`dashboard-harian`** (`DashboardHarianSnapshotService`, `dashboard_harian_snapshots`):
     - Menggabungkan data pinjaman, simpanan, target RKA, dan recovery secara periodik harian.
  6. **`core` & `access-control`**:
     - Registry laporan terpusat di `nama_report`, anggaran di `rka`, unit kerja di `referensi_uker`.
     - Scoping akses data bertingkat (Kanwil, Kanca, Unit) wajib dipatuhi di setiap query analitik.
  7. **Performa SQL**:
     - Selalu tulis query yang SARGable (`where periode = ?`), manfaatkan covering index, hindari N+1 query.
     - Panggil `$this->releaseSessionLockIfNeeded()` di controller analitik berat agar sesi PHP tidak memblokir antrean request AJAX.

## Safety Rules & Rollback Prevention

- **DO NOT run destructive Git commands**: NEVER run Git commands that discard unstaged local changes, delete unstaged files, or reset the working directory (e.g., `git checkout -- <file>`, `git checkout <file>`, `git reset`, `git reset --hard`, `git stash`, `git clean`) on user-facing source, view, controller, test, or config files (such as `resources/views/...`, `app/...`, `routes/...`, `config/...`, `tests/...`) without explicit user permission.
- **Respect Local Overrides**: The user frequently makes manual, unstaged cosmetic, styling, or logical modifications. You must respect these changes. If you see modified files on startup, do not assume they should be reverted.
- **Targeted Undoing**: If you need to revert or modify changes you made during your turn, surgically use file editing tools (`replace_file_content` or `multi_replace_file_content`) to revert only the specific line blocks you introduced. Do not discard the entire file's history or other files' histories using Git.
- **Obtain Permission**: If you absolutely must reset or clean any part of the workspace, explain the situation to the user first and obtain explicit confirmation.

# Coding Agent Rules

Kamu adalah coding agent yang rapi, hati-hati, dan efektif.

## Prinsip utama
1. Jangan pernah mengubah file, logic, flow, API, schema, import/export, delete report, atau behavior lain yang tidak secara eksplisit diminta.
2. Perubahan harus sekecil mungkin, fokus pada task yang diminta.
3. Jangan mengatakan “sudah oke” sebelum melakukan validasi nyata.
4. Jika tidak bisa menjalankan test/build/lint, katakan jelas bahwa validasi belum dilakukan.

## Sebelum mengubah kode
- Pahami scope task.
- Identifikasi file yang perlu diubah.
- Jangan refactor besar tanpa diminta.
- Jangan membersihkan kode, rename, reorder import, atau mengubah formatting global kecuali perlu untuk task.

## Saat implementasi
- Pertahankan existing behavior.
- Jangan mengubah logic import report / delete report / report lain kecuali user secara eksplisit meminta.
- Jangan menghapus kode yang tampak tidak terpakai tanpa konfirmasi.
- Jangan membuat asumsi bisnis logic. Jika ambigu, pilih perubahan paling minimal.

## Validasi wajib
Setelah coding:
1. Jalankan test terkait.
2. Jalankan lint/typecheck/build jika tersedia.
3. Jika ada test gagal, investigasi dan perbaiki.
4. Jangan klaim selesai jika hanya membaca kode tanpa testing.

## Format jawaban akhir
Selalu jawab dengan:
- Ringkasan perubahan
- File yang diubah
- Validasi yang dijalankan
- Hasil validasi
- Risiko / bagian yang belum tervalidasi

## Larangan
- Jangan bilang “harusnya sudah benar” tanpa bukti.
- Jangan menyentuh logic lain hanya karena terlihat bisa diperbaiki.
- Jangan mengubah dependency, config, migration, atau struktur folder tanpa instruksi eksplisit.
- Jangan membuat perubahan spekulatif.
# Adaptive Multi-Agent Collaboration Protocol (Audit, Planning, Writing, Testing, Design)

- **Delegasi Adaptif**: Lead AI menilai kompleksitas, risiko, independensi pekerjaan, dan relevansi role sebelum menentukan tingkat orkestrasi **L0-L5**. Lead boleh bekerja tanpa subagent (L0) atau melibatkan satu hingga seluruh lima role (L1-L5); level menunjukkan kebutuhan orkestrasi, bukan kewajiban menyalakan role yang tidak relevan.
- Pilih hanya role yang relevan (`planning_agent`, `design_agent`, `writing_agent`, `testing_agent`, `audit_agent`). Subagent tidak wajib berjalan simultan dan dapat ditambah atau dikurangi ketika temuan baru mengubah risiko atau scope.
- Validasi nyata tetap wajib dan dilakukan oleh Lead atau `testing_agent`. Instruksi eksplisit user dan skill yang berlaku memiliki prioritas dalam pemilihan/delegasi agent.
- Aturan larangan destructive Git dan perlindungan perubahan lokal tetap berlaku untuk Lead maupun seluruh subagent.
- Referensi lengkap konfigurasi dan alur: `.agents/rules/multi-agent-orchestration.md`.

# Rules for Gemini 3.6
- Selalu lakukan analisis kode multi-file sebelum menjawab.
- Jangan gunakan tebakan, jalankan `code_execution` jika ragu.
- Tulis output dalam bahasa Indonesia yang teknis dan padat

# Token Optimization Rule (MarkItDown) - Wajib untuk Antigravity, VS Code, Claude, Codex, Gemini
- **DILARANG** membaca file dokumen mentah (`.xlsx`, `.xls`, `.docx`, `.pptx`, `.pdf`, `.html`, `.csv`) secara binary atau dump teks penuh ke dalam context window AI karena sangat memboroskan token.
- **WAJIB** konversi terlebih dahulu menjadi Markdown ringkas sebelum dianalisis:
  - Jalankan: `python scripts/markitdown_token_optimizer.py <path_file>` atau `php artisan doc:markdown <path_file>`
  - Untuk langsung salin ke clipboard Windows pengguna: tambahkan flag `-c` atau `--clipboard`
  - Gunakan teks Markdown bersih hasil konversi sebagai satu-satunya bahan pembacaan konteks dokumen oleh AI.
