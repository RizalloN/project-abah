# Audit pembacaan landing Pinjaman — 9 Oktober 2026

## Lanjutan: perpindahan tanggal dan kesiapan worker

Perbaikan lanjutan menambahkan cache agregat rincian SML Mikro per bulan/cabang/versi sumber. Pergantian tanggal harian tidak lagi memindai ulang seluruh histori akhir bulan yang sama. Cache berlaku dua hari, berubah saat versi pinjaman/harian berubah, dan dilewati saat refresh manual. Bulan tanpa baris sumber tidak disimpan sebagai nol. Angka yang disimpan adalah agregat sumber, bukan hasil persentase atau koreksi statis.

Pengukuran MySQL lokal terbaru, tetap melewati cache payload utama:

- Pembacaan sumber yang berhasil: **30.164 ms**.
- Pembacaan ulang dengan agregat bulanan: **166 ms**, nol query rincian SML Daily Loan.
- Filter tanggal **2026-10-08 → 2026-10-07**: **180 ms**, nol query rincian SML Daily Loan.
- Payload lengkap pembacaan sumber dan cache identik; sama dengan baseline sebelumnya. Seri kualitas saat pindah tanggal juga identik karena memakai akhir bulan yang sama.
- Percobaan cold pertama dihentikan batas statement profil 60 detik; percobaan berikutnya berhasil. Pengukuran cache tidak membuktikan jalur cold telah cepat.

`WarmDashboardSimpananCacheJob` untuk tipe landing sekarang mengantre ulang 60 detik ketika lock/dependensi belum siap, maksimal 20 percobaan, tanpa mengubah antrean atau mekanisme worker. `WarmLandingLoanRiskCacheJob` memperoleh lock per periode dan mengantre ulang hasil unavailable/pending, sehingga tidak dianggap selesai hanya karena fungsi sudah kembali. Lock build landing di worker meliputi timeout job.

`LandingPayloadState` mempertahankan status pending dari dependensi bertingkat. Ranking KTS/tunggakan kecil meneruskan status tersebut, cache portfolio pending hanya disimpan 30 detik dan tidak menimpa stable, dan pemulihan halaman melihat dependensi tersebut. Tidak memakai data tanggal/cabang lain untuk menutupi sumber kosong.

Validasi lanjutan: **188 tes PHP / 1.567 assertions**, **18 tes JavaScript**, build frontend, kompilasi Blade, syntax PHP, dan diff check lulus. Tes meliputi lock contention, antre ulang lalu sukses, perlindungan stable, invalidasi versi sumber, refresh manual, serta isolasi cabang. Fixture cache pada `WarmReportJobUniquenessTest` diperbarui agar menyatakan `available=true` sesuai kontrak cache valid. Setelah pembatasan perubahan job hanya ke tipe landing, 10 tes worker/uniqueness dijalankan lagi dan lulus.

File tambahan/lanjutan: `app/Support/LandingPayloadState.php`, `app/Jobs/WarmDashboardSimpananCacheJob.php`, `app/Jobs/WarmLandingLoanRiskCacheJob.php`, controller dashboard, analytics service, partial `landing-page-recovery`, skrip profil, dan tes terkait. Perubahan lokal `.gitignore`, `.mcp.json`, serta skrip audit RM tidak disentuh. Worker produksi tidak direstart dan browser terautentikasi belum diuji; penerapan pada proses worker yang sudah berjalan bergantung pada siklus reload worker.

## Ruang lingkup dan bukti

Audit mencakup halaman awal `DashboardSimpananController::index`, sumber ringkasan/snapshot, panel SME/Konsumer/Mikro, analitik kualitas/tarif, cache risiko, prognosa, dan pemanggilan ulang JavaScript. Rumus bisnis, import/delete report, dependency, dan indeks database tidak diubah. Status Git sebelum pekerjaan bersih.

Profil dilakukan terhadap MySQL lokal, dengan cache payload aplikasi dilewati melalui builder asli. Batas waktu setiap statement profil 60 detik; tidak mengosongkan buffer database atau cache produksi. Angka berikut adalah pengukuran lokal, bukan SLA maupun benchmark beban multiuser.

| Jalur | Sebelum | Sesudah | Bukti/keterangan |
|---|---:|---:|---|
| Build analitik SME | 16.470 ms | 599 ms | Payload lengkap sebelum/sesudah identik |
| Pencarian tanggal SME | 14.988 ms | probe per akhir bulan <1 ms masing-masing pada pengukuran sesudah | Sebelumnya DISTINCT seluruh tanggal setahun; sekarang EXISTS pada maksimal 12 tanggal yang benar-benar dipakai |
| Resolver periode Mikro SSA | 725 ms query | sekitar 9 ms keseluruhan resolver | MAX dengan filter menghasilkan table scan; ORDER BY tanggal DESC LIMIT 1 memakai jalur tanggal |
| Agregasi kualitas SSA Mikro | 1.308 ms | 42 ms | Filter tanggal akhir bulan dilakukan di SQL, bukan setelah agregasi semua tanggal |
| Build analitik Mikro keseluruhan | 29.395 ms | 36.001 ms | Belum membaik secara keseluruhan; rincian SML Daily Loan mendominasi dan bervariasi akibat I/O |

Seluruh payload SME dan Mikro dibandingkan secara struktural, bukan hanya angka tampilan; keduanya identik. Artefak lokal: `storage/app/landing-audit-{sme,micro}-{baseline,optimized}.json`. Reproduksi: `php scripts/audit-landing-loan.php profile sme <label>`, `profile micro <label>`, `schema`, atau `explain micro`.

## Peta pembacaan dan tindakan

| Bagian | Pembacaan / risiko | Tindakan |
|---|---|---|
| Halaman awal | Cache payload -> Harian -> snapshot pinjaman -> Daily Loan; lock 20 detik dan fallback membangun ulang setelah gagal mendapat lock | Lock cold build 300 detik; request pesaing mengembalikan pending yang dapat dicoba ulang, bukan menjalankan build berat kedua; session dilepas sesudah scope ditetapkan |
| Ringkasan pinjaman | SQL agregasi tanpa GROUP BY tetap menghasilkan objek saat tidak ada baris snapshot | COUNT(*) menentukan keberadaan sumber; snapshot kosong melanjutkan ke Daily Loan, saldo nol dengan baris sumber tetap sah |
| Cabang terbesar | Keberadaan tabel snapshot sebelumnya menutup fallback meski periode kosong | Hasil snapshot kosong melanjutkan pembacaan Daily Loan |
| Ringkasan putusan | Refresh menghapus durable; hasil pending dapat dipublikasikan sebagai durable | Durable dipertahankan; validator periode/available/error/pending dipakai pada HTTP dan worker; pending diberi jeda 30 detik |
| Analitik SME/Konsumer | Enumerasi seluruh tanggal tahunan, lalu hanya akhir bulan yang digunakan | Probe EXISTS tanggal akhir bulan dengan filter segmen/cabang yang sama; rumus SUM, tarif H-1, dan realisasi closing tidak berubah |
| Analitik Mikro | SSA menjadi total kualitas; Daily Loan memberi proporsi SML 1–3 | SSA hanya membaca akhir bulan; query segmen kosong dihilangkan; query rincian SML dipertahankan setelah percobaan pemisahan per bulan terbukti lebih lambat |
| Cache analitik | Request cold bersamaan dapat menghitung payload yang sama berulang | Atomic build lock; request pesaing mendapat pending; pembacaan cache diulang setelah lock diperoleh |
| SME operasional | Delegasi kuadran ke Kinerja RM; durable valid dan job pemanasan sudah tersedia | Kontrak sumber/formula dipertahankan; retry browser diuji |
| Konsumer | Pipeline Google Sheets dapat menunggu 20 detik, kemudian KPR 20 detik; stable baru diperiksa setelah kegagalan | Stable ditampilkan sebelum jaringan, refresh ditunda dengan guard, kegagalan diberi cache pendek 30 detik |
| Mikro operasional | Invalidasi versi biasa tidak memeriksa durable sebelum rebuild | Durable periode/cabang yang sama ditampilkan lalu diperbarui |
| PN mismatch, produktivitas RM KUR, pipeline Mikro | Kegagalan tambahan dapat menggagalkan seluruh HTML panel | Isolasi masing-masing pembaca; data utama tetap dikirim dan ditandai pending agar retry tetap berjalan |
| Risiko KTS/tunggakan kecil | Agregasi unit dan cabang; cabang memakai WITH ROLLUP untuk menjaga COUNT DISTINCT area | Sumber kosong tidak menimpa last-valid atau menaikkan generasi cache; saldo nol bersumber tetap valid. Formula/distinct tidak diubah |
| Prognosa | Refresh remote dilakukan di jalur awal walaupun tersedia hasil terakhir | Cache khusus landing fresh/last-valid, refresh tertunda dengan guard dan jeda kegagalan; API presentasi tidak berubah |
| Browser | Respons pending dapat mengganti panel valid; prefetch bersaing dengan request aktif | Pertahankan HTML terakhir, status awal analitik jelas, retry tetap terbatas; prefetch menunggu request aktif dan berjalan berurutan |

Indeks `daily_loan_dinamis`, `ssa_pinjaman`, dan `dashboard_pinjaman_snapshots` diperiksa. Tidak ditambah atau dihapus. EXPLAIN rincian SML Mikro memilih `idx_snapshot_filter_optimized`, estimasi sekitar 5 juta baris. Memisahkan sembilan bulan menjadi sembilan query membutuhkan sekitar 49,9 detik sehingga tidak diadopsi. Mempercepat bagian ini lebih jauh memerlukan rancangan agregasi persisten atau indeks yang diuji beserta biaya import/storage, bukan perubahan indeks spekulatif.

## Validasi dan batasan

- Suite landing terarah terakhir: 174 tes / 1.507 assertions lulus, termasuk regresi lock, isolasi sumber tambahan, dan perlindungan durable ringkasan putusan.
- Runtime JavaScript: 18 tes lulus, mencakup pending, error sementara, autentikasi, pergantian periode, dan urutan prefetch.
- PHP syntax, `git diff --check`, `npm.cmd run build`, dan `php artisan view:cache` berhasil.
- Suite lebih luas menemukan tiga kegagalan `LandingSimpananCardTest`: ekspektasi baseline payroll/perusahaan anak dan rendering. Ketiganya muncul juga ketika controller HEAD sebelum perubahan dimuat pada bootstrap uji terpisah. Logika Simpanan tersebut tidak disentuh.
- Pint melaporkan masalah format di file lama (termasuk line endings dan spasi operator); tidak dilakukan format massal.
- Browser lokal mengalihkan `/dashboard` ke `/login`. Belum ada bukti end-to-end setelah autentikasi, pergantian cabang/segmen di browser produksi, atau uji beban multiuser.
- Cold pertama sumber eksternal tanpa last-valid masih menunggu jaringan. Callback deferred dapat tetap memakai worker Apache setelah respons. Mekanisme queue pemanasan yang sudah ada tetap memerlukan worker aktif.
- Tidak menjanjikan semua data selalu tersedia: sumber memang kosong, jaringan gagal, dan cold query Mikro masih merupakan batasan nyata. Perubahan mencegah sejumlah kegagalan tersebut menghapus data valid atau menggagalkan seluruh panel.

## File implementasi

`app/Http/Controllers/DashboardSimpananController.php`; `app/Support/LandingLoanAnalyticsService.php`; `LandingConsumerOperationalService.php`; `LandingMicroPerformanceService.php`; `LandingLoanRiskCacheService.php`; `LandingPrognosaCardService.php`; `resources/views/dashboard.blade.php`.

Tes ditambah/diperbarui pada `LandingAvailabilityIsolationTest`, `LandingLoanSourceFallbackTest`, `LandingLoanAnalyticsServiceTest`, `LandingConsumerOperationalServiceTest`, `LandingMicroPerformanceServiceTest`, `LandingLoanRiskCacheServiceTest`, `LandingPrognosaAvailabilityTest`, serta `scripts/landing-recovery-test.mjs`. Skrip profil: `scripts/audit-landing-loan.php`. Knowledge graph dibangun kembali setelah perubahan sumber.
