# Analisis kelayakan perhitungan KPI Mikro dan SME 2026

Tanggal audit: 15 Oktober 2026. Basis pemeriksaan angka: posisi 30 September 2026; inventaris periode diperiksa sampai data terakhir yang tersimpan saat audit. Cakupan: database lokal project-ABAH, kode sumber, snapshot workbook September, dan cache sumber KPI yang sudah tersedia. Tidak dilakukan perubahan rumus, import, sumber transaksi, target, maupun skor aplikasi.

## Keputusan

**Belum dapat menghasilkan seluruh KPI individual secara lengkap, independen, dan terverifikasi dari data transaksi yang tersedia sekarang.** Namun, banyak realisasi dasar dapat dihitung. Sebagian skor dapat ditampilkan atau direproduksi dari workbook/Google Sheet yang sudah berisi hasil perhitungan.

Ketiganya harus dibedakan:

1. **Menampilkan hasil resmi dari sumber:** tersedia untuk MBM, KA Unit, Mantri Tumbuh, dan Mantri Briguna September; bukan bukti bahwa aplikasi mempunyai seluruh bahan pembentuk skor.
2. **Menghitung realisasi dari transaksi:** paling siap untuk posisi OS, kualitas kredit pada satu tanggal, dan posisi simpanan pada level Unit/KC. Metrik individual, historis, dan lintas produk memerlukan pemeriksaan tambahan.
3. **Menghasilkan KPI final yang dapat diaudit:** memerlukan realisasi lengkap, target sah, bobot sesuai jabatan/tipe Unit, periode dan kepemilikan yang benar, serta aturan scoring. Belum lengkap untuk seluruh tujuh jabatan yang diminta.

Tidak diberikan persentase kesiapan keseluruhan: keberadaan kolom atau jumlah indikator tidak membuktikan bobot KPI yang benar-benar telah terpenuhi.

## Sumber acuan

- PDF SME `Lampiran_Surat_untuk_KPI_Individual_Segmen_SME_Tahun_2026-124.pdf`, 15 halaman: matriks jabatan/bobot h.1–2; glossary h.3–11; rating h.13; product holding h.14–15.
- PDF Mikro `B 364 MBD BST Guidance KPI Jajaran Bisnis Mikro Tahun 2026 (1).pdf`, 9 halaman: tipe Unit dan matriks Mantri h.1–3; definisi/target h.4–6; KPI MBM/KA Unit dan penjelasannya h.7–8; pengesahan breakdown target h.9.
- PDF SME berhasil diekstrak melalui MarkItDown. PDF Mikro adalah scan tanpa lapisan teks: MarkItDown menghasilkan kosong, kemudian dilakukan OCR lokal seluruh sembilan halaman, disimpan menjadi Markdown. Tabel kecil/berwarna tidak semuanya terbaca pasti; angka yang ambigu tidak dijadikan ketetapan baru.
- Ketentuan dokumen dipakai sebagai acuan bisnis analisis, bukan instruksi untuk menjalankan perubahan aplikasi.

## Bukti ketersediaan data

Periode harian berikut dihitung berdasarkan tanggal kalender 1 Januari–30 September 2026, sebanyak 273 hari. Ini pemeriksaan keberadaan posisi, **bukan** bukti setiap posisi sudah lengkap untuk seluruh rekening/Unit.

| Sumber | Bukti yang diperiksa | Implikasi |
|---|---|---|
| `daily_loan_dinamis` | 71 posisi seluruh histori; 55/273 tanggal pada YTD September, termasuk 26/30 tanggal September. Semua closing Januari–September tersedia. Terakhir 14 Oktober. | Kuat untuk posisi akhir bulan; belum cukup untuk daily average individual yang sesungguhnya, termasuk average September secara tersendiri. |
| `ssa_pinjaman` | 270/273 tanggal YTD September; seluruh closing tersedia. | Agregat kredit Unit/segmen hampir harian, tidak mempunyai CIF dan PN untuk menghitung rata-rata per RM. |
| `ssa_simpanan` | 270/273 tanggal YTD September; seluruh closing tersedia. | Mendukung saldo dan tren agregat Unit; perlu melengkapi tanggal yang hilang untuk average eksak. |
| Kedua SSA di atas | Tanggal tidak tersedia: 16 Agustus, 24 Agustus, 13 September 2026. | Tidak boleh mengisi nol atau mengganti denominator tanpa dasar kebijakan. |
| `simpanan_multipn` | 21 nilai posisi termasuk satu NULL; 16 tanggal pada YTD September. Semua closing Januari–September tersedia. Terakhir 8 Oktober. | Posisi CIF tersedia bulanan; bukan histori harian lengkap. NULL tidak dipakai sebagai periode sah. |
| `simpanan_multipn`, 30 September | 2.796.333 baris; 2.041.376 CIF; satu regional office dan empat KC. | **Belum memenuhi cakupan Selindo** untuk rasio DPK kelolaan SME. |
| `lw325_ph` | 64 posisi seluruh histori; semua closing Januari–September tersedia. Terakhir saat audit: 13 Oktober. | Ada bahan PH, tetapi tidak selalu sejajar dengan posisi Daily Loan/SSA terbaru. |
| `cognos_recovery` | 14 posisi; terakhir Februari 2026. Ada `recovery_klaim` dan `recovery_non_klaim`, tidak ada PN petugas. | Pemisahan recovery klaim/nonklaim periode berikutnya dan atribusi individual belum lengkap. |
| `gi405_recovery` | Tersedia sampai 14 Oktober; nilai pendapatan recovery per kode Unit. | Mendukung agregat Unit; tidak membuktikan pembayaran klaim atau kredit kepada individu tertentu. |
| `rka` | 26.529 baris tahun 2026; 239 mata anggaran; empat KC, 111 label uker. | Banyak target agregat tersedia. Bukan master target KPI per PN dan per bulan. |
| `performance_targets` | Kategori terisi: BRIGUNA-KONSUMER 10 baris, KPR 3, TEST 1. | Tidak ditemukan master target KPI lengkap untuk jabatan Mikro/SME yang diminta pada tabel ini. |
| `ssa_almafacts` | Data sampai September; ada laba sebelum/sesudah pajak serta `Fee & Pendapatan Lainnya`. | Laba Unit berpeluang dihitung; total fee tersebut belum membuktikan komponen FBI khusus guidebook. |

Realisasi kredit 30 September mempunyai 318.666 baris: Micro 297.651, Small 3.885, Medium 6, Consumer 17.124. Pemeriksaan kolom identitas seluruh posisi itu menemukan CIF kosong 0, PN pengelola kosong/penanda kosong 154, dan PN pemrakarsa kosong/penanda kosong 12.455. Angka tersebut **bukan** audit kesesuaian seluruh PN dengan roster; hanya kelengkapan nilai dasar.

## Cakupan pegawai dan referensi KPI

Roster BRIHC Area 6 saat audit memuat 554 Mantri, 2 Mantri Briguna, 23 Mantri Recovery, 45 RM Bisnis Kecil, 7 RM SME Quality, dan 13 RM Mikro. Jumlah ini adalah keadaan roster saat audit, bukan otomatis populasi eligible sepanjang tahun.

Snapshot workbook September berisi:

| Jabatan | Profil sumber | Yang tersedia | Batasan |
|---|---:|---|---|
| Mantri Tumbuh | 464 | Tipe Unit, JG, PN, status, seri BC bulanan, pencapaian dan skor per indikator. | Seluruh profil berstatus PT. Tidak mencakup seluruh roster Mantri 554. Selisih populasi tidak otomatis kesalahan: periode, eligibility, dan kontrak harus dicocokkan. |
| Mantri Briguna | 2 | Profil serta lima kelompok pencapaian/skor. | Bukan rincian transaksi dan target sah pembentuk angka. |
| KA Unit | 101 | Pencapaian/skor, termasuk laba, FBI, fraud, dan audit. | Hasil akhir dapat ditampilkan; komponen mentah dan dasar target tidak semuanya ada. |
| MBM | 12 | Pencapaian/skor sepuluh kelompok indikator. | Memerlukan histori wilayah supervisi untuk merekonstruksi periode sebelumnya. |

Pada 464 profil Mantri Tumbuh, tipe Unit tercatat: Tipe 1 sebanyak 96, Tipe 2 sebanyak 19, Tipe 3 sebanyak 163, Tipe 4 sebanyak 186. JG4: 77, JG5: 200, JG6: 187. Dengan demikian **sebagian master tipe/JG sudah ada** dan tidak perlu diminta ulang dari nol; perlu dilengkapi cakupan serta masa berlakunya.

Cache Google Sheet RM SME yang tersedia saat audit memuat 488 baris utama, 61 baris target, dan roster 50 orang. Setelah diproses melalui layanan KPI yang ada:

- Periode terbaru yang berisi realisasi adalah **Juli 2026**, bukan September.
- Juni dan Juli: masing-masing 48 dari 50 profil memiliki tujuh pasangan realisasi–target yang terbaca numerik lengkap. Dua profil masih mempunyai nilai/jumlah debitur atau target yang kosong/tidak terbaca; kosong tidak diasumsikan nol.
- Januari–Mei: booking value chain dan product holding belum terisi pada seluruh 50 profil; ada kekosongan lain pada debitur baru/target.
- Kesamaan jumlah baris atau isian numerik bukan bukti kebenaran rumus, ketepatan atribusi PN, ataupun coverage transaksi Selindo.
- Ada selisih antara roster sumber KPI 50 dan roster BRIHC RM Bisnis Kecil 45. Perlu rekonsiliasi PN, peran, tanggal mutasi dan eligibility; jangan menyatukan berdasarkan nama saja.

## Matriks kelayakan menurut jabatan

| Jabatan | Bisa dilakukan dari data sekarang | Mengapa belum final/sempurna |
|---|---|---|
| MBM | Monitoring OS/kualitas/simpanan wilayah, sebagian recovery dan kanal transaksi; menampilkan KPI September sumber. | Average harian belum penuh; tabungan debitur harus dibedakan dari total tabungan; produktivitas harus mengikuti kriteria kumulatif/MOB; target dan histori wilayah supervisi perlu disahkan. |
| KA Unit | OS, SML/NPL, posisi tabungan, kandidat laba Unit, EDC/QRIS, aktivitas BRILink; menampilkan hasil KPI September. | FBI spesifik, rincian fraud dan audit tidak tersedia; aturan kanal/target perlu disamakan; definisi laba harus dipastikan. |
| Mantri Tumbuh | Bahan nett Kupedes/KUR, OS/kualitas per PN, beberapa kanal merchant; tipe Unit/JG tersedia pada snapshot September. | Nett harus direkonsiliasi pada event/CIF; NTB/NTL belum terbukti; relasi rekening tabungan–pinjaman dan target individu belum lengkap. |
| Mantri Briguna | Menampilkan dua profil KPI September; bahan transaksi Briguna Mikro tersedia untuk penyusunan perhitungan. | Helper nett Mantri yang ada mengecualikan Briguna, sehingga tidak dapat dipakai langsung. Pisahkan Briguna Mikro dari Briguna Konsumer; butuh target, NTB/NTL, tabungan terelasi, atribusi historis. |
| Mantri Recovery | Roster 23 orang dan bahan kualitas/PH ada. | Belum ada master lengkap Unit I versus I+E/keberadaan Field Collection, alokasi portofolio bertanggal, dan recovery/klaim per PN. Bukan sekadar menjumlahkan kredit dengan PN prakarsa sendiri. |
| RM SME | Posisi OS/kualitas per tanggal, bahan mutasi kualitas; reproduksi skor sumber untuk sebagian periode/profil. | Daily average individual tidak lengkap; seluruh flag value chain pada sampel posisi kosong; product holding 35 produk belum lengkap; DPK Selindo belum tersedia; realisasi sumber terbaru baru Juli. |
| SBM | Agregasi portofolio Small/KC dan sebagian produktivitas RM dari data sekarang. | Perlu master supervisi SBM–RM bertanggal dan target resmi; downgrade rata-rata, PH, DPK Selindo, serta booking value chain masih perlu dilengkapi. Menu/skema skor SBM mandiri belum ditemukan dalam registry KPI yang diperiksa. |

RM SME bukan satu formula untuk semua peran. Guidebook membedakan RM Small, RM Medium, dan RM Small Quality. RM Small Recovery dalam BRIHC tidak otomatis identik dengan RM Small Quality; membutuhkan penetapan organisasi resmi.

## Matriks indikator Mikro

Status **bisa** di bawah berarti realisasi pada posisi yang tersedia dapat dibentuk, belum otomatis berarti skor final siap.

| Indikator | Status realisasi | Sumber yang ada | Data/ketentuan yang harus dilengkapi |
|---|---|---|---|
| Posisi OS Mikro per Unit/MBM | Bisa untuk tanggal yang lengkap | SSA Pinjaman, Daily Loan, snapshot Harian. | Hierarki Unit–MBM bertanggal, cakupan produk, target RKA sesuai wilayah. |
| Nett disbursement Kupedes/KUR | Sebagian; bahan dan kalkulator ada | Daily Loan/LW321PN, histori rekening/CIF, `MicroNettDisbursementCalculator`. | Event booking/suplesi, OS pengurang yang sah, pelunasan dan rebooking dalam bulan yang sama, PN penerima kredit, histori/periode lengkap. Jangan menggunakan delta total OS atau gross plafond sebagai pengganti nett. |
| Nett disbursement Briguna Mikro | Sebagian | Daily Loan dan sumber KPI Briguna. | Perhitungan khusus produk/segmen, benchmark nominatif, target JG/breakdown; helper non-Briguna tidak langsung berlaku. |
| Debitur Mikro baru | Sebagian | CIF, tanggal realisasi, kredit historis. | CIF master bertanggal untuk NTB, histori fasilitas lintas segmen untuk NTL, atau daftar NTB/NTL resmi. Jumlah rekening baru/rebooking tidak sama dengan debitur baru. |
| Rasio/posisi tabungan debitur kelolaan | Sebagian | CIF pinjaman dan simpanan; baseline Desember 2025 tersedia pada closing. | Relasi rekening tabungan–pinjaman yang diakui, bukan seluruh CASA CIF; saldo tabungan tanggal sama; baseline dan target tier per individu. Tabel Daily Loan yang diperiksa tidak mempunyai nomor rekening tabungan relasi. |
| % Kol 2 dan % Kol 3–5 | Bisa secara Unit; bersyarat per individu | Kolektibilitas/OS pada Daily Loan dan SSA. | Populasi kelolaan I/I+E/T+I/T+I+E, mutasi portofolio bertanggal, denominator yang disahkan, target. |
| Nett downgrade SML | Bisa secara Unit pada dua posisi lengkap; bersyarat individual | Posisi SML bulan berjalan dan sebelumnya. | Guidebook: SML bulan laporan dikurangi SML bulan sebelumnya. Gunakan kepemilikan yang konsisten, target bulanan dari portofolio yang ditetapkan. |
| Nett downgrade NPL | Sebagian | Posisi NPL dua bulan dan daftar PH. | Guidebook: NPL sekarang − NPL sebelumnya + PH. PH harus nominal kejadian pada periode, bukan saldo PH kumulatif; perlu atribusi PN dan kelengkapan kedua cutoff. |
| Recovery ekstrakomtabel | Sebagian; agregat Unit lebih siap | GI405, Cognos Recovery, LW325PH. | Pembayaran pokok aktual, tanggal, rekening, klaim/nonklaim, PN yang memperoleh kredit, pembagian bersama, tipe Unit tanpa Field Collection. Tidak semua penurunan saldo PH adalah recovery kas. |
| Recovery dari klaim | Belum lengkap | Cognos memiliki kolom klaim, LW325 memiliki flag/nilai klaim. | Ledger pembayaran klaim per rekening/tanggal/PN; sumber Cognos setelah Februari belum ada. Flag klaim bukan bukti pembayaran diterima. |
| Penambahan QRIS/EDC produktif | Sebagian; merchant tersedia | EDC detail mempunyai TID/CIF/PN/SV; QRIS mempunyai PAN/CIF/PN/SV. | Cutoff dan baseline penambahan, kriteria SV bulanan, atribusi petugas/kelolaan, deduplikasi, sektor perdagangan untuk denominator target. |
| BRILink aktif | Bisa dihitung ulang pada sumber bulan yang ada | Transaksi per outlet/Unit. | Guidebook minimum satu transaksi finansial/bulan. Sumber harus memastikan transaksi yang dihitung adalah transaksi finansial. Aturan laporan saat ini berbeda; Januari 2026 tidak ada pada tabel summary yang diperiksa. |
| Agen BRILink produktif/BEP/Jawara/Juragan | Sebagian | Transaksi dan fee outlet, beberapa tier sudah dihitung aplikasi. | Ketetapan tier KPI resmi dan periode berlaku; definisi tiap tier belum lengkap dalam dua PDF yang diberikan. Tidak menyamakan fee/nominal dengan seluruh ketentuan produktivitas tanpa validasi. |
| Average Balance Tabungan | Sebagian | SSA Simpanan agregat hampir harian; Simpanan Multi-PN hanya posisi tertentu. | Saldo harian 1 Januari–cutoff lengkap, penanganan hari non-operasional resmi, scope tabungan Mikro, target average. Bukan rata-rata beberapa closing. |
| Posisi Tabungan/DPK Mikro | Bisa secara Unit pada closing yang tersedia | SSA Simpanan dengan segmen/produk. | Bedakan total DPK, tabungan Mikro, dan tabungan debitur Mikro terelasi; target RKA untuk indikator yang benar. |
| Laba Unit | Kandidat kuat, perlu konfirmasi basis | Almafacts mempunyai laba sebelum dan sesudah pajak. | Penetapan laba yang dimaksud KPI, granularity Unit, dan mapping ke target. PDF Mikro menyebut laba tetapi tidak merinci pilihan sebelum/sesudah pajak. |
| FBI Mikro | Sebagian; belum cukup untuk komponen resmi | Total fee Almafacts; target FBI PA/Non-PA di RKA; beberapa fee kanal. | Realisasi ledger FBI Non-PA sesuai daftar h.7, AMKKM BRILife dan Asuransi Mikro BRINS per Unit/periode; aturan rekonsiliasi dan penghindaran hitung ganda. Target bukan realisasi. |
| Produktivitas Mantri | Sebagian; jangan langsung memakai KPI insentif | Nett bulanan, roster, modul MOB/insentif. | Guidebook menyebut rata-rata kumulatif Rp800 juta/bulan, kualitas MOB6 batas 1%, target produktivitas 70%; perlu histori kohort, eligibility dan denominator periodik. Modul insentif memakai jendela kuartal/tiga bulan: bukan otomatis formula KPI tahunan. |
| Fraud internal dan rating audit | Hasil sumber September bisa ditampilkan; belum dapat dihitung mandiri | Pencapaian/skor workbook KA Unit. | Kejadian fraud valid per Unit/periode, hasil audit, status final/tanggal berlaku, skala konversi dan aturan koreksi. `user_audit_log` adalah log aplikasi, bukan rating audit bisnis. |

### Temuan kanal yang terukur

- EDC 30 September: 1.479 baris dan 1.479 TID unik; 813 baris memenuhi SV minimal Rp15 juta. Kolom CIF dan PN pemrakarsa terisi pada sampel posisi ini. Ini belum membuktikan seluruh atribusi sesuai PN kelolaan atau seluruh SV sudah berbasis bulan yang sama.
- QRIS 30 September: 68.371 baris, 68.367 PAN unik; ada 7 CIF dan 844 PN pemrakarsa kosong/penanda kosong. Selisih empat baris terhadap PAN unik perlu rekonsiliasi, tidak langsung dihapus. PN pemrakarsa tidak otomatis sama dengan Mantri pemilik portofolio kredit.
- Guidebook menetapkan EDC Rp15 juta/TID/bulan dan QRIS Rp50 ribu/QRIS/bulan; target merchant juga mempertimbangkan jumlah debitur sektor perdagangan. Kolom sektor ekonomi nominatif tidak ditemukan pada Daily Loan yang diperiksa; CRAS mempunyai sektor tetapi grain agregatnya tidak menyediakan CIF/PN.
- BRILink September: 5.895 outlet/baris mempunyai `total_transaksi >= 1`; hanya 4.894 memenuhi `total_nominal >= Rp50.000` yang dipakai laporan sekarang. Selisih **1.001 outlet** membuktikan kedua aturan tidak ekuivalen. Ini belum merupakan penetapan angka KPI resmi: jenis transaksi dan eligibility tetap harus diverifikasi.

## Matriks indikator SME

Matriks bobot yang jelas dari PDF untuk RM Small dan SBM:

| Indikator | RM Small | SBM |
|---|---:|---:|
| Avg Balance Small | 10% | 10% |
| Posisi OS Small | 15% | 15% |
| Debitur Small baru | 10% | 10% |
| Downgrade to Kol 2 | 20%* | 15% |
| Net downgrade to NPL | Opsi pengalihan* | 15% |
| Rasio DPK debitur kelolaan to Loan SME | 20% | 10% |
| Booking Value Chain Cash Loan | 20% | 15% |
| Product Holding | 5% | — |
| Produktivitas RM SME | — | 10% |
| Total bobot | 100% | 100% |

\* Guidebook memperbolehkan pengalihan sebagian bobot ke Downgrade to NPL sebesar 10% sesuai rekomendasi Group Bisnis. Pengalihan tidak boleh diaktifkan berdasarkan tebakan atau diterapkan kepada semua RM otomatis.

RM Medium mempunyai bobot tersendiri: Avg Medium 15%, OS Medium 25%, debitur baru Medium 10%, Kol2 10%, Kol3–5 10%, DPK 15%, value chain 10%, product holding 5%. RM Small Quality: Kol2 15%, LR 15%, downgrade NPL kelolaan 25%, penurunan OS LAR PL 25%, upgrade/unflag LAR PL 20%.

| Indikator | Status dan bahan yang ada | Kekurangan agar sesuai guidebook |
|---|---|---|
| Posisi OS Small/Medium | Bisa untuk tanggal tersedia: rekening, segmen, OS dan PN ada. Small termasuk cash collateral. | Scope portofolio/PN dan mutasi harus disahkan; jangan menyamakan hanya label Small yang mengeluarkan cash collateral. Target individu dari sumber yang sah. |
| Average Balance Small/Medium | Belum dapat dihitung eksak individual dari Daily Loan lokal: 55/273 posisi YTD. SSA lebih lengkap tetapi tidak mempunyai PN. | Saldo harian per rekening/CIF/PN lengkap, atau realisasi official average per PN. Guidebook memakai total saldo harian/jumlah hari, bukan rata-rata atas tanggal yang kebetulan tersedia. |
| Debitur Small/Medium baru | Tanggal realisasi/CIF mendukung kandidat; sumber KPI mempunyai agregat debitur. | Penetapan definisi baru SME dari Group Bisnis, histori lintas rekening, rebooking/suplesi/mutasi, deduplikasi CIF. Glossary SME hanya menyebut debitur baru; jangan memaksakan definisi NTB/NTL Mikro kepada SME tanpa dasar. |
| % Kol2 dan % Kol3–5 | Bisa sebagai rasio stock pada posisi lengkap. | Denominator portofolio, transfer ownership, target dan ketentuan score minimize. |
| Downgrade to Kol2 | Sebagian: ada kualitas per rekening dan beberapa posisi historis. | Guidebook menyebut **average OS downgrade Kol1→Kol2 / average Kol1**. Ini bukan %SML saat ini dan bukan nett delta SML ala Mikro. Perlu histori transisi dan metode average/cutoff resmi. |
| Net downgrade to NPL | Sebagian: NPL, kolektibilitas dan PH tersedia. | Guidebook: (downgrade PL→NPL + PH) − (upgrade NPL→PL + lunas), atau delta NPL + PH sesuai rekonsiliasi. Butuh kejadian PH/lunas/upgrade dan portofolio konsisten. Rekening hilang dari file tidak otomatis lunas. |
| DPK debitur kelolaan to Loan SME | Dapat menghitung rasio lokal berbasis CIF sebagai monitoring parsial. | Guidebook h.9 mensyaratkan DPK **tanpa batas Unit Kerja/Selindo**. Lokal hanya empat KC. DPK juga tidak sama dengan CASA: deposito harus diperlakukan sesuai definisi DPK, bukan memakai pembilang Giro+Tabungan dari laporan CASA. |
| Booking Value Chain Cash Loan | Bahan denominator booking ada; numerator belum dapat dibuktikan. | Pada posisi 30 September, seluruh 3.891 baris Small+Medium mempunyai `flag_commodity_chain1 = NULL`. Butuh daftar booking resmi terflag value chain/program pemerintah, tanggal, nominal cash loan, rekening/CIF, PN, dan aturan pengakuan booking. Pipeline/prospek value chain belum merupakan realisasi booking. |
| Product Holding | Sebagian produk dapat ditautkan lewat CIF: kredit, simpanan, QRIS, EDC, beberapa kanal digital. | Daftar kepemilikan seluruh produk guidebook per CIF/periode, aturan produk aktif dan deduplikasi. Daftar guidebook memuat 35 entri, termasuk produk investasi, asuransi, trade finance/BG dan lain-lain yang belum tersedia sebagai inventory CIF lengkap. |
| Produktivitas RM SME (SBM) | Ada roster, nilai realisasi, kualitas dan kuadran aplikasi. | Master supervisi SBM–RM bertanggal, populasi eligible, dan ketentuan produktivitas/kuadran resmi. Dua PDF ini tidak memberi parameter nominal/kuadran SME secara lengkap. Kuadran aplikasi tidak otomatis bukti ketentuan resmi tersebut. |
| LR, penurunan LAR PL, downgrade NPL kelolaan, upgrade/unflag (RM Small Quality) | Bahan sebagian ada pada flag restruk, kualitas dan histori rekening. | Formula LR pada glossary = OS restruk lancar/total OS restruk; bukan LR/seluruh kredit. LAR PL = SML+LR, bukan seluruh LAR termasuk NPL. Perlu kohort kelolaan quality, log unflag/restrukturisasi dan average transisi. Tidak dapat mengganti dengan rasio LAR snapshot biasa. |
| FBI SME | Target dan sebagian laporan agregat ada; tidak merupakan KPI langsung RM Small/SBM pada matriks ini. | Item FBI dalam matriks tersebut untuk RSBH; jangan menambahkan bobot FBI ke RM Small/SBM. Untuk perluasan ke RSBH diperlukan realisasi FBI kredit, TF/BG dan bancassurance SME, bukan total fee lintas segmen. |

## Ketentuan yang tidak boleh disamakan

1. **Monitoring produktivitas dan KPI individual:** target panel operasional/insentif bukan master target KPI. Ada konstanta target di laporan produktivitas; target resmi individu dapat berbeda menurut JG, RKA Unit, mutasi dan SK breakdown.
2. **Tipe jabatan dan tipe Unit:** T/I/I+E/T+I/T+I+E serta ketersediaan Field Collection menentukan indikator/bobot. Mantri Recovery tidak boleh dimasukkan ke roster Tumbuh hanya agar jumlahnya cocok.
3. **Tabungan terelasi Mikro, CASA, DPK SME:** ketiganya pembilang berbeda. Join CIF lokal memberi sebagian informasi, bukan otomatis tabungan relasi atau DPK Selindo.
4. **Average dan posisi:** posisi akhir bulan tidak menggantikan daily average. Seluruh closing tersedia tidak berarti seluruh tahun tersedia harian.
5. **Flow dan stock:** nett disbursement ≠ perubahan OS; debitur baru ≠ rekening baru; pembayaran klaim ≠ rekening berflag klaim; PH periode ≠ seluruh saldo PH.
6. **Kualitas Mikro dan SME:** delta SML Mikro berbeda dari average downgrade Kol1→Kol2 SME. Penggunaan rumus yang sama akan mengubah makna KPI.
7. **Ketersediaan data dan hasil nol:** nilai belum tersedia harus ditandai belum tersedia; bukan nol, bukan diberi skor nol final dan bukan bobot indikator lain dinaikkan otomatis.
8. **Skor sumber dan skor independen:** snapshot workbook mempertahankan hasil tersimpan, tidak menjalankan ulang formula Excel. Kehadiran pencapaian/skor tidak membuktikan nominal realisasi, target dan event lengkap.

Guidebook SME menyebut capping 110% dan warna merah <95%, kuning 95%–<99,9%, hijau 99,9%–110%. Rumus minimize pada target/realisasi nol atau negatif dan ketentuan pengalihan bobot harus dipastikan sebelum membakukan scoring. Jangan menerapkan capping SME kepada seluruh Mikro tanpa ketentuan yang berlaku.

Dalam kode yang diperiksa, beberapa ringkasan layanan SME menggunakan kelompok skor 100/85/70 untuk statistik. Kelompok analitik tersebut tidak identik dengan rating warna guidebook di atas. Ini temuan pemetaan, belum dilakukan perubahan UI/scoring.

Pada jalur laporan PH yang diperiksa terdapat alokasi 30% untuk sebagian rekening berflag klaim. Angka estimasi/alokasi tersebut tidak cukup dijadikan bukti transaksi klaim individual sesuai guidebook tanpa rekonsiliasi ledger.

## Paket data tambahan yang diperlukan

| Prioritas | Paket | Kolom minimum dan granularitas | Menutup kebutuhan |
|---|---|---|---|
| 1 | Master target dan kebijakan scoring resmi | Tahun/bulan, PN, jabatan, JG, BC, indikator, target, satuan, bobot, polaritas, cap/floor, pilihan bobot, tipe Unit, nomor/tanggal SK, berlaku mulai–akhir, persetujuan. | Semua skor final. Gunakan target JG guidebook sebagai minimum/acuan, bukan otomatis target resmi. |
| 1 | Master penugasan efektif | PN, jabatan, BC/Unit/KC, supervisor MBM/SBM/KA Unit, tipe T/I/E dan Field Collection, tanggal mulai–akhir, eligible/tidak serta alasan. | Denominator, mutasi, role berbeda dan roll-up supervisor. Seri BC workbook dapat menjadi bahan awal, bukan master lengkap seluruh pegawai. |
| 1 | Histori kredit lengkap + event | Posisi harian, rekening, CIF, produk/segmen, OS, plafon, kolek, flag restruk, PN prakarsa/pengelola, tanggal booking/suplesi/lunas/PH/unflag, nominal event, pasangan fasilitas lama–baru. | Average per RM, nett, kualitas/transisi, MOB, kualitas SME dan atribusi PN. Untuk kohort perlu histori sebelum Januari 2026 juga. |
| 1 | NTB/NTL resmi atau master CIF/fasilitas | CIF, tanggal CIF pertama, tanggal pinjaman pertama seluruh segmen/Mikro/SME sesuai ketetapan, status baru tahun berjalan, rekening, produk, PN penerima kredit. | Debitur baru yang dapat diaudit. |
| 1 | DPK Selindo dan relasi tabungan | Posisi, CIF, rekening, jenis simpanan, saldo, Unit pembuka, nomor rekening pinjaman terelasi, jenis relasi, mulai–akhir relasi. | DPK SME Selindo, tabungan kelolaan Mikro, baseline Des25 dan posisi/average tabungan debitur. |
| 1 | Recovery dan klaim aktual | Tanggal posting, rekening/CIF, jenis pokok/bunga/klaim/subrogasi, nominal, reversal, produk/segmen, Unit, PN yang dikreditkan, aturan pembagian dan portofolio. | Recovery I/I+E/T+I+E, klaim, rekonsiliasi PH. |
| 2 | Booking value chain/program pemerintah | Tanggal booking, rekening/CIF, PN, program/anchor/flag resmi, nominal cash loan yang diakui, reversal/cancel, cutoff dan pembanding total booking. | Numerator value chain SME. |
| 2 | Product holding seluruh produk | Posisi, CIF, ID produk baku, status aktif/eligible, tanggal mulai–akhir, asal sumber. | Product holding lengkap tanpa mengalikan jumlah rekening atau hitung ganda produk. |
| 2 | FBI dan laba sesuai komponen | Bulan, Unit/KC, akun GL/jenis fee, PA/Non-PA, produk/segmen, nominal/reversal, laba dan definisi yang disahkan. | FBI Mikro, laba KA Unit; potensi perluasan FBI RSBH. |
| 2 | Merchant/agen dan sektor perdagangan | Bulan, TID/PAN/outlet, CIF, PN dan jenis atribusi, Unit, SV bulan, transaksi finansial, fee, tanggal pertama produktif, baseline, status/tier resmi; daftar CIF debitur sektor perdagangan. | Pertambahan EDC/QRIS, denominator target merchant, BRILink aktif/produktif. Banyak kolom sudah tersedia, fokus pada coverage dan definisi yang kurang. |
| 2 | Fraud dan rating audit | Unit, periode/tanggal berlaku, jenis temuan, status final, nilai/rating, penetapan koreksi, skala skor KPI. | Dua indikator nonfinansial KA Unit. |

## Tahapan yang realistis

1. Pertahankan hasil KPI workbook/Sheet sebagai **sumber resmi eksternal**, dengan label periode/cakupan. Juni/Juli SME yang terisi dan September Mikro dapat dipakai untuk rekonsiliasi, tidak diposisikan sebagai hasil perhitungan mandiri transaksi.
2. Bangun realisasi otomatis yang paling siap: OS, Kol2/NPL dan posisi simpanan level Unit/KC; kemudian per PN setelah identity/assignment lolos pemeriksaan. Tetap tampilkan status kelengkapan dan target sah.
3. Selesaikan perbedaan definisi sumber yang terukur: BRILink aktif, tabungan vs CASA vs DPK, flow vs stock, periode data tidak sejajar, dan kosong vs nol.
4. Lengkapi master target/penugasan serta data average, NTB/NTL, recovery, Selindo, value chain dan product holding. Tanpa paket ini, seluruh skor final tetap harus berstatus parsial.
5. Uji setiap indikator terhadap workbook resmi **dan nominatif pembentuknya**, kemudian validasi skor tertimbang serta roll-up Unit–MBM/SBM. Simpan versi ketentuan, identitas sumber, cutoff dan rincian rekonsiliasi. Jangan menyatakan lengkap hanya karena total bobot 100% atau unit test lulus.

## Klarifikasi dokumen sebelum implementasi final

- PDF Mikro merujuk surat produktivitas Mantri 2026, Ketentuan Kredit Mikro B.497.e-MBD/03/2026, serta surat DSG yang tidak seluruh isinya dilampirkan. Ketentuan tersebut diperlukan untuk definisi operasional/eligibility lengkap.
- Angka tertentu dalam tabel scan Mikro tidak terbaca konsisten, terutama beberapa bobot, batas tier dan simbol perbandingan MOB. Sebelum hardcode bobot/target, gunakan salinan tabel yang lebih jelas atau SK breakdown resmi. Contoh OCR membaca target Briguna JG5 5 debitur/bulan dengan 96/tahun, dan JG6 6/bulan dengan 108/tahun; ini perlu pemeriksaan naskah asli, tidak dikoreksi otomatis menjadi 60/72.
- Daftar product holding SME memuat Briguna di dua nomor. Perlu katalog produk resmi agar tidak menghitung produk yang sama dua kali atau memutuskan sendiri apakah itu dua varian berbeda.
- Formula average downgrade SME belum menjelaskan seluruh kalender pengukuran dan perlakuan perpindahan kelolaan; ketentuan Group Bisnis dibutuhkan.

## Validasi dan batas pemeriksaan

- Inventaris schema aktual, periode sumber, kolom identitas, sampel agregasi posisi lengkap 30 September, roster Area 6, coverage target, header dan populasi workbook, serta cache sumber SME diperiksa langsung.
- Query profil memakai batas waktu per statement 35 detik. Tidak menambah/mengubah index. Inventaris periode CRAS pada kolom tanggalnya dilewati untuk menghindari scan penuh tanpa leading index; angka kelengkapan histori CRAS tidak diklaim.
- Data merchant/BRILink yang disebut adalah hasil seluruh posisi/bulan yang dipilih, bukan sampling beberapa baris.
- Tiga suite terkait sumber KPI/roster lulus: **14 tests, 711 assertions**. Ini memverifikasi kontrak implementasi yang ada, bukan sertifikasi kecocokan seluruh formula dengan guidebook.
- Bukti tersimpan di `storage/app/kpi-guidebook-audit/`: `database-inventory.json`, `coverage.json`, `profile.json`, `workbook-summary.json`, `sme-cached-sources.json`, Markdown hasil ekstraksi, serta script audit read-only. Hanya agregat/metadata audit yang disimpan; tidak membuat ekspor rekening/nama nasabah.
- Aplikasi tidak diubah; build UI tidak diperlukan. Hasil merepresentasikan database lokal dan cache yang tersedia saat audit, bukan pemeriksaan tersendiri ke database produksi atau pembaruan langsung Google Sheet.
- Knowledge graph diperbarui untuk navigasi sesuai instruksi repository; sumber kode dan database tetap menjadi dasar kesimpulan.
