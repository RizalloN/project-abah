# Rule: Multi-Agent Orchestration Protocol (Audit, Planning, Writing, Testing, Design)

Dokumen ini adalah aturan permanen orkestrasi multi-agent adaptif untuk pengembangan dan pemeliharaan di `project-ABAH`.
Agent utama (Lead Orchestrator) menentukan penggunaan **0 sampai 5 subagent** berdasarkan kompleksitas, risiko, independensi pekerjaan, dan relevansi spesialis. Tidak semua tugas harus didelegasikan dan tidak semua role harus aktif pada setiap tugas.

---

## 1. Lima Subagent Spesialis & Peran

| Subagent | Type Name | Tools & Hak Akses | Fokus & Tanggung Jawab Utama |
|---|---|---|---|
| **Planning Agent** | `planning_agent` | Read-only | Menavigasi Repository Knowledge Graph (`php artisan knowledge:graph`), menganalisis dampak antardomain (19 domain), mengidentifikasi edge-cases, dan merumuskan rencana implementasi bedah minimal tanpa refactor berlebihan. |
| **Design Agent** | `design_agent` | Write & MCP | Bertanggung jawab atas UI/UX, estetika Blade templates, visualisasi Chart.js, layout responsif, modal interaktif, standarisasi angka/rupiah (`formatNumber()`), dan kejelasan terminologi perbankan di tampilan web. |
| **Writing Agent** | `writing_agent` | Write tools | Eksekutor kode bedah (surgical edits). Mengubah kode secara presisi menggunakan `replace_file_content` / `write_to_file` tanpa mengubah formatting global, menjaga style eksisting, dan mematuhi prinsip *least-change*. |
| **Testing Agent** | `testing_agent` | Write & Run | Menjalankan suite PHPUnit (`tests/Unit`, `tests/Feature`), membuat test regresi baru untuk mencegah bug terulang, serta memvalidasi kesegaran graph (`php artisan knowledge:graph --check`). |
| **Audit Agent** | `audit_agent` | Read-only | Melakukan audit keamanan, mendeteksi redudansi indeks MySQL (left-prefix coverage pada tabel raksasa `simpanan_multipn` & `daily_loan_dinamis`), memeriksa SARGability query SQL, pelepasan session lock (`releaseSessionLockIfNeeded`), dan risiko rollback/destructive git. |

---

## 2. Penilaian dan Tingkat Orkestrasi Adaptif

Lead menilai tugas sebelum eksekusi dan boleh menaikkan atau menurunkan level ketika scope atau risiko berubah. Jumlah file bukan satu-satunya ukuran; pertimbangkan dampak bisnis, lintas domain/layer, kebutuhan spesialis, peluang kerja paralel, serta biaya kesalahan.

| Level | Penggunaan Subagent | Contoh Karakteristik Tugas |
|---|---:|---|
| **L0** | 0 | Tugas trivial, jawaban singkat, inspeksi read-only sederhana, atau perubahan sangat kecil dan jelas yang aman dikerjakan Lead. |
| **L1** | 1 | Satu concern terbatas yang memperoleh manfaat nyata dari satu spesialis. |
| **L2** | 1-2 | Perubahan kecil hingga menengah pada satu atau dua area dengan analisis atau verifikasi terpisah. |
| **L3** | 2-3 | Bug/fitur normal multi-file atau lintas layer yang membutuhkan kombinasi perencanaan, implementasi, desain, dan/atau pengujian. |
| **L4** | 3-4 | Perubahan lintas domain atau berisiko tinggi, misalnya import, database besar, queue, akses data, keamanan, atau rekonsiliasi angka. |
| **L5** | Seluruh 5 role | Perubahan arsitektur/insiden kritis yang benar-benar mencakup perencanaan, UI/UX, implementasi, pengujian, dan audit. |

Level adalah batas dan panduan kebutuhan orkestrasi, bukan kuota yang memaksa role tidak relevan. Subagent terpilih boleh berjalan paralel hanya jika subtugasnya independen; bila kapasitas terbatas, jalankan bertahap.

### Pemilihan Role

- Gunakan `planning_agent` untuk scope ambigu, dampak lintas domain, atau kebutuhan pemetaan Knowledge Graph.
- Gunakan `design_agent` hanya jika tugas menyentuh UI/UX, Blade, chart, responsive layout, atau terminologi tampilan.
- Gunakan `writing_agent` jika implementasi layak dipisahkan dan batas file/ownership dapat dibuat jelas.
- Gunakan `testing_agent` untuk penyusunan atau eksekusi validasi yang substansial, paralel, atau berisiko regresi tinggi.
- Gunakan `audit_agent` untuk database/index/schema, query berat, import, keamanan, akses data, tindakan destruktif, atau risiko integritas data.

---

## 3. Aturan Pelaksanaan & Komunikasi Subagent

1. **Keputusan Lead**:
   - Lead mencatat penilaian level secara proporsional, memilih hanya role relevan, dan tetap menjadi integrator serta penanggung jawab hasil akhir.
   - Lead boleh melakukan eskalasi atau de-eskalasi selama tugas berlangsung berdasarkan bukti baru.
2. **Pendelegasian Terarah**:
   - Gunakan mekanisme subagent yang tersedia pada environment. Berikan prompt tajam, batas file dan ownership yang jelas, serta output yang dapat diverifikasi.
   - Hindari overlap write antarsubagent. Secara default subagent bersifat read-only kecuali diberi kewenangan write yang eksplisit dan terbatas.
3. **Prinsip Non-Destruktif**:
   - Baik Lead Agent maupun Writing/Testing subagents **DILARANG KERAS** menjalankan git rollback destructive (`git checkout --`, `git reset --hard`, `git clean`) terhadap perubahan lokal user (termasuk file yang sedang dimodifikasi manual oleh user seperti `app/Services/Import/ImportExecutionService.php`).
4. **Verifikasi Nyata Sebelum Klaim**:
   - Validasi nyata tetap wajib pada semua level, termasuk L0. Validasi dapat dilakukan langsung oleh Lead atau didelegasikan kepada `testing_agent`.
   - Klaim selesai harus didukung hasil test, lint/build, pemeriksaan runtime, atau bukti relevan lain; keberadaan subagent bukan bukti validasi.
5. **Prioritas Instruksi**:
   - Instruksi eksplisit user dan instruksi skill yang sedang digunakan dapat mewajibkan role atau pola delegasi tertentu dan harus dipatuhi.
   - Jika fasilitas subagent tidak tersedia, Lead menjalankan pekerjaan secara langsung dengan guardrail dan validasi yang sama serta menyatakan keterbatasannya bila material.
