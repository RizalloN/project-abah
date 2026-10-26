# Data PH: rekonsiliasi GI405

> **Diperbarui 23 Oktober 2026 (keputusan pengguna).** Halaman Data PH menampilkan recovery, rincian segmen, TUPOK, LUNAS, Non Klaim, Klaim, pembanding dan delta dari **LW325 PH** (fallback Cognos), sesuai logika controller. GI405 **tidak ditampilkan** di halaman. Pilihan periode dan periode default mengikuti LW325/Cognos.
>
> GI405 dipakai sebagai **pengecekan backend**: `DataPhReportController::gi405Reconciliation()` membandingkan Total Recovery LW325 bulan berjalan per KC dengan pendapatan GI405 sejak posisi GI405 terakhir bulan sebelumnya (reset Januari diperhitungkan). Toleransi: selisih ≤5% sesuai, ≤10% perhatian, >10% tidak sesuai; tanpa posisi GI405 pada tanggal yang sama berstatus belum tersedia. Hasilnya tampil sebagai item **Rekonsiliasi Data PH ↔ GI405** di health check Kelola Job (cache 15 menit) dan dapat dijalankan manual: `php artisan data-ph:reconcile-gi405 [--period=YYYY-MM-DD]`. Saat diperbarui: 20 Okt 2026 Area 6 −2,53% (keempat KC sesuai); 30 Sep 2026 Ponorogo −23,27% dan Ngawi −6,70% tertandai. Aturan GI405 di bawah (tanda akuntansi, reset Januari, nol sah, sumber kontrol/Single Row) tetap berlaku untuk pengecekan ini.

Perubahan 16 Oktober 2026. Scope hanya Data PH; import, snapshot Dashboard Harian, dan laporan lain tidak diubah.

## Dasar perhitungan

- Total recovery mengambil posisi pendapatan kumulatif tahun berjalan dari `gi405_recovery`, dengan tanda akuntansi dibalik sekali: `-SUM(pendapatan_koreksi_ppap_dr_angsuran_ph)`.
- Bila kontrol tersebut belum tersedia untuk suatu Unit/periode, gunakan GI405 Single Row yang sesuai: hanya IDR dan posting control `*POST`, saldo `ending_balance` bertanda. `equivalents_idr` pada data IDR ini kosong.
- Sampai Desember 2025, akun `418020000010` dan akun pendapatan klaim pokok `418020000013` membentuk total. Pemisahan klaim/nonklaim dipakai hanya bila akun klaim eksplisit tersedia pada periode tersebut dan kedua komponen cocok dengan kontrol Unit sampai sen.
- Sesuai klarifikasi pengguna, mulai Januari 2026 acuan Recovery DH adalah `418020000010`, Pendapatan Koreksi PPAP-dr Angsuran PH. Akun klaim historis tidak ditambahkan ke fallback 2026. Tidak menggunakan saldo Titipan Klaim.
- Nilai nol adalah realisasi sah; tidak diganti angka LW325/Cognos hanya karena nilainya nol. Koreksi debit tidak dibuang dan tidak diubah menjadi positif dengan `ABS()`.
- Pemilihan tanggal selalu persis periode yang diminta, bukan tanggal lain yang lebih baru. Service hanya menyimpan memo selama instance/request berjalan.

## Rincian yang dapat dibuktikan

GI405 tidak mempunyai rincian produk yang setara dengan Micro, Small, Briguna Konsumer, dan KPR. Cost center tidak cukup untuk memisahkannya. Total ditampilkan dalam baris **GI405 — belum dirinci per segmen**, tanpa membagi angka memakai rasio LW325.

Sumber 2026 yang diperiksa tidak mempunyai posting pendapatan klaim terpisah. Total Recovery DH tetap ditampilkan penuh, sedangkan pemisahan klaim/nonklaim yang tidak tersedia ditandai `n/d`, bukan dianggap nol. Sumber Cognos Januari/Februari 2026 tidak cocok dengan kontrol GI405 sehingga tidak dipakai untuk memaksakan pembagian tersebut.

TUPOK/LUNAS juga tidak dirinci GL. Saldo dan nominatif **Sisa PH** tetap dari LW325. Nominatif tersebut bukan bukti transaksi pendapatan GI405. Jika tidak ada saldo LW325 pada tanggal pendapatan terbaru, keadaan itu diberi keterangan tersendiri.

Jika GI405 belum tersedia pada periode/filter, jalur estimasi LW325 dan sumber Cognos lama tetap dipertahankan serta diberi label sumber. Alokasi 30% pada fallback historis tidak menjadi dasar realisasi GI405.

## Pembanding dan pergantian tahun

- M-1 dalam tahun yang sama: pendapatan kumulatif sekarang dikurangi pendapatan kumulatif pembanding.
- M-1 Januari: sama dengan realisasi Januari, karena saldo pendapatan direset pada awal tahun.
- Delta YTD untuk GI405: realisasi kumulatif tahun berjalan, bukan dikurangi Desember tahun sebelumnya.
- Posisi Desember sebelumnya tetap dapat ditampilkan sebagai pembanding tersendiri.
- Pembanding GI405 yang tidak tersedia tidak diganti estimasi LW325/Cognos dengan basis berbeda untuk menghasilkan selisih numerik.
- Tanggal GI405 ikut masuk pilihan periode dan unit recovery tetap muncul walaupun tidak mempunyai sisa PH pada LW325.

## Bukti runtime

Audit pada database lokal 16 Oktober 2026:

- 244 posisi GI405.
- 27.042 pemeriksaan Unit dan 975 pemeriksaan KC melalui metode Data PH yang dipakai aplikasi.
- Tidak ada selisih sampai sen terhadap kontrol GI405.
- Audit independen Single Row: 1.775 pasangan Unit–periode pada 16 posisi overlap cocok dengan kontrol GI405.

Posisi 15 Oktober 2026:

| KC | Recovery DH |
|---|---:|
| Madiun | Rp52.181.079.727,94 |
| Magetan | Rp39.873.102.570,81 |
| Ngawi | Rp21.805.654.082,72 |
| Ponorogo | Rp73.642.140.739,44 |
| **Area 6** | **Rp187.501.977.120,91** |

Blade aktual berhasil dirender untuk 15 Oktober 2026, 31 Desember 2025 dan 31 Januari 2026. Preview dari render tersebut diperiksa di browser pada desktop 1366px dan ponsel 390px: jumlah kolom/rowspan konsisten, total muncul, tabel dapat digeser, dan tidak ada overflow halaman. Ini bukan pengujian browser produksi terautentikasi.

Regresi mencakup nol, koreksi bertanda, akun/currency/posting yang tidak relevan, split historis, mismatch ledger vs kontrol, sumber ganda, filter/akses cabang, tanggal khusus GI405, Unit tanpa sisa PH, pergantian tahun, pembanding berbeda sumber, dan nominatif LW325: **70 test / 409 assertion lulus**. PHP lint dan Pint berkas baru lulus; controller lama mempunyai pelanggaran format Pint yang juga dikonfirmasi pada baseline HEAD dan tidak dibersihkan massal. Build aset berhasil.

Bukti tanpa nominatif nasabah disimpan di `storage/app/ph-gi405-ledger-audit.json` dan `storage/app/ph-gi405-validation.json`. Script audit hanya membaca database. Tidak menambah indeks dan tidak mengubah data sumber.
