# Review Teknis Project ABAH

## Ringkasan Nilai

- Nilai keseluruhan: `7.5/10`
- Kesan umum: fondasi proyek sudah kuat, terutama di area import, queue, snapshot, dan caching. Kualitas belum merata karena area reporting lama masih terlalu berat di controller dan lebih rentan regresi.

## Kekuatan Utama

### 1. Fondasi framework sudah modern

- Laravel 12 dan PHP 8.2 sudah cukup up to date.
- Dependency inti masih relatif ramping.
- Struktur folder mengikuti pola Laravel dengan cukup konsisten.

### 2. Import pipeline cukup matang

Area import terlihat sebagai bagian yang paling serius dibangun.

- Sudah ada pemisahan service seperti:
  - `ImportExecutionService`
  - `ImportPipelineService`
  - `ImportProgressService`
  - `MySqlBulkLoadService`
  - `SchemaIntrospectionService`
- Ada dukungan queue, staged import, fallback flow, dan progress streaming.
- Ada test unit yang memverifikasi perilaku queue dan stale job.

### 3. Performa backend mulai dipikirkan

Beberapa keputusan teknis menunjukkan awareness performa yang bagus.

- Ada snapshot table untuk report tertentu.
- Ada cache versioning global.
- Ada middleware `ReleaseSessionLockMiddleware` untuk mengurangi blocking session pada request GET.
- Ada sync service untuk invalidasi cache dan rebuild snapshot setelah import.

### 4. Test suite sudah ada dan bukan formalitas

- Terdapat puluhan file test.
- Coverage terlihat cukup bagus pada service yang penting, terutama import dan sinkronisasi data.
- Ini memberi fondasi yang baik untuk refactor lebih lanjut.

### 5. Security baseline sudah mulai diperhatikan

- Ada `SecurityHeadersMiddleware`.
- Ada role middleware untuk admin route.
- Ada throttle di beberapa route penting.

## Masalah Utama

### 1. Controller report terlalu besar dan terlalu banyak tanggung jawab

Masalah terbesar proyek ini ada di `app/Http/Controllers/DataReportController.php`.

- File sangat besar.
- Query SQL, kalkulasi bisnis, shaping response JSON, dan logika filter bercampur dalam satu class.
- Sulit dites secara terisolasi.
- Sulit direview karena perubahan kecil bisa punya dampak ke banyak report lain.

Dampak:

- Bug lebih mudah lolos.
- Onboarding developer baru jadi lebih berat.
- Refactor makin mahal jika dibiarkan.

### 2. Ada indikasi bug logika di total BRIMO

Di bagian tab BRIMO pada `DataReportController`, struktur array total tidak konsisten.

- Key yang diinisialisasi berbeda dengan key yang dipakai saat akumulasi.
- Perhitungan total `mtd`, `ytd`, `yoy`, dan `yoy_pct` berisiko salah.
- Potensi warning `Undefined array key` juga ada.

Ini termasuk temuan prioritas tinggi karena langsung mempengaruhi hasil report.

### 3. Pola query beberapa report masih boros

Contoh yang paling jelas ada di `PerformanceBrimoController`.

- Satu item branch/uker memicu beberapa query terpisah.
- Saat jumlah item filter membesar, total query ikut meledak.
- Ini bisa terasa lambat di data besar walaupun sekarang mungkin masih terasa aman.

Masalah seperti ini biasanya tidak langsung terlihat di lokal, tapi akan terasa saat user banyak atau data historis bertambah.

### 4. Arsitektur proyek belum konsisten

Ada dua dunia dalam proyek ini:

- Dunia baru: service-oriented, ada test, ada cache/snapshot, lebih rapi.
- Dunia lama: controller besar, query inline, logic-heavy endpoint.

Ketimpangan ini membuat codebase terasa setengah matang, bukan karena jelek, tapi karena standar kualitasnya belum rata.

### 5. Repo masih kotor oleh file debug dan helper sementara

Di root project masih ada file seperti:

- `debug_casa.php`
- `test_pdo.php`
- `test_fix.php`
- `.tmp_*`
- `.codex_tmp_*`

Risiko:

- Membingungkan saat maintenance.
- Berpotensi ikut ke deploy bila proses release tidak ketat.
- Karena app berjalan dari `htdocs`, file PHP di root juga punya risiko terekspos via web server.

## Nilai per Aspek

- Arsitektur: `7/10`
- Maintainability: `6.5/10`
- Performa backend: `8/10`
- Keamanan dasar: `7.5/10`
- Kualitas testing: `8/10`
- Kerapihan repo: `6/10`
- Kesiapan scale internal: `7.5/10`

## Prioritas Perbaikan

### Prioritas 1

- Perbaiki bug total BRIMO.
- Bersihkan file debug/test sementara dari root project.
- Tambahkan ignore pattern yang sesuai di `.gitignore`.

### Prioritas 2

- Pecah `DataReportController` per domain report.
- Pindahkan query dan kalkulasi ke service/query builder khusus.
- Tambahkan test untuk report yang paling rawan: BRIMO, new payroll, kolaborasi perusahaan anak.

### Prioritas 3

- Optimalkan report yang masih memakai pola N+1 query.
- Standarkan naming dan pola return payload untuk semua endpoint report.
- Tambahkan dokumentasi internal singkat tentang alur import -> sync -> snapshot -> report.

## Rekomendasi Struktur Refactor

Contoh arah refactor yang aman:

- `DataReportController`
  - pecah menjadi:
  - `DigitalPerformanceReportController`
  - `NewPayrollReportController`
  - `KolaborasiReportController`
- Tambahkan service:
  - `BrimoReportService`
  - `NewPayrollReportService`
  - `KolaborasiSnapshotReportService`
- Tambahkan test:
  - `BrimoReportServiceTest`
  - `NewPayrollReportServiceTest`
  - `KolaborasiSnapshotReportServiceTest`

Pendekatan ini paling aman karena bisa dilakukan bertahap tanpa memaksa rewrite besar sekaligus.

## Kesimpulan

Project ini bukan project asal jadi. Secara teknis, fondasinya sudah menunjukkan pemikiran yang serius, terutama di area import dan sinkronisasi data. Nilai proyek turun bukan karena teknologinya tertinggal, tetapi karena sebagian area reporting masih membawa beban desain lama yang membuat maintenance dan debugging lebih sulit.

Kalau area report dirapikan dan bug perhitungan ditutup, proyek ini realistis naik ke kisaran `8.5/10`.
