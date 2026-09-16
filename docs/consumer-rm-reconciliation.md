# Rekonsiliasi RM Consumer terhadap Kanwil

Audit database dan dua workbook pengguna pada 9 September 2026. Workbook dipakai
sebagai acuan pengujian; nilainya tidak dimasukkan ke kalkulator/snapshot.

**Status 10 September 2026:** posisi Daily Loan 21 dan 25 Juli telah dipulihkan
ke arsip database (34.070 rekening/posisi). Setelah rebuild, error Juli 5,2472%
dengan 9/12 kuadran cocok; total Januari-Agustus 83/96 kuadran cocok. Target 1%
belum tercapai. Angka pada bagian v26/v28 di bawah merekam kondisi sebelumnya.

**Pembaruan 14 September 2026 (`consumer-cif-event-v2`):** kalkulator Briguna
sekarang membentuk satu event per produk/CIF/posisi pertama setelah tanggal
realisasi. Realisasi baru memakai plafon bruto ketika CIF produk tersebut tidak
memiliki saldo akhir bulan sebelumnya. Suplesi memakai
`MAX(0, total OS CIF pada posisi event - total OS CIF akhir bulan sebelumnya)`;
seluruh rekening lama dalam CIF ikut baseline. KPR tetap dihitung terpisah
sebagai plafon bruto maksimum per rekening unik.

Booking dengan PN pengelola dan pemrakarsa kosong tidak lagi dibuang. Jika PN
terisi pada posisi berikutnya sampai cutoff, booking memakai atribusi tersebut;
jika tetap kosong, nilainya masuk `PN BELUM TERISI` per cabang/unit. Pada posisi
12 September, total 169 rekening sama dengan tabel agregat pengguna: 162 sudah
teratribusi dan 7 belum (Madiun 1/Rp30 juta, Magetan 1/Rp200 juta, Ponorogo
5/Rp608.519.594). Snapshot hasil rebuild cocok 35/35 kelompok Consumer.

Nominal Area 6 aplikasi Rp16.512.174.886 masih lebih tinggi Rp582.174.886 dari
acuan agregat Rp15.930 juta. Selisih ini tidak di-hardcode. Sumber tidak memuat
pasangan rekening pelunasan/jenis transaksi Kanwil; `jenis_restruk1` pada
booking September hanya `N` atau kosong. Contoh Novan 11 September: plafon baru
Rp25 juta, sedangkan perubahan seluruh saldo CIF dari akhir Agustus ke posisi
event Rp18.337.898 karena beberapa fasilitas lama turun/hilang; acuan harian
tetap Rp25 juta. Nominatif transaksi Kanwil diperlukan untuk membedakan
pelunasan pasangan, pelunasan lain, dan gagal lunas secara deterministik.

## Menjalankan ulang

```powershell
php artisan snapshot:validate-rm --period=2026-03-31 --kanwil-reference="C:\Users\msi\Downloads\kuadran rm briguna.xlsx" --max-error-percent=1
```

Tambahkan `--json` untuk rincian baru, suplesi, total, jumlah rekening, kuadran,
dan konsistensi snapshot. Gunakan `--max-error-percent=0.5` untuk toleransi 0,5%.
Exit code 1 berarti acuan gagal dipenuhi atau input tidak valid, bukan otomatis
berarti command mengalami error runtime. Tanpa `--kanwil-reference`, command
tetap menggunakan validasi snapshot terhadap sumber seperti sebelumnya.

Mode acuan memerlukan akhir bulan tahun 2026. Parser membaca sheet `RM - Sort`,
nilai formula yang tersimpan, dan nominal dalam juta; tidak menghitung ulang
atau membuka referensi eksternal. Identitas ambigu, nilai tidak tersedia,
target tidak valid, atau total/kuadran acuan tidak konsisten menolak audit.

## Kriteria kelulusan

- Jumlah rekening total, baru, dan suplesi harus sesuai per RM.
- Error total, baru, dan suplesi harus memenuhi toleransi masing-masing per RM.
- Kuadran harus identik sekalipun error nominal masih di bawah toleransi.
- Snapshot harus sama dengan kalkulator; RM realisasi di luar acuan dilaporkan.
- Acuan nol dengan realisasi bukan nol tidak dianggap error 0%.
- Error tertimbang = jumlah nilai absolut selisih per RM / jumlah nominal acuan.
  Selisih positif dan negatif tidak saling meniadakan.

## Hasil produksi formula v26

| Bulan 2026 | Kuadran cocok | Error nominal tertimbang |
| --- | ---: | ---: |
| Januari | 12/12 | 3,0811% |
| Februari | 12/12 | 1,8863% |
| Maret | 9/12 | 3,4942% |
| April | 12/12 | 4,6999% |
| Mei | 11/12 | 5,5426% |
| Juni | 8/12 | 5,9135% |
| Juli | 8/12 | 7,3435% |
| Agustus | 9/12 | 4,3925% |

Total kuadran cocok: 81/96 (84,375%). Seluruh total snapshot cocok dengan
kalkulator. Kecocokan tersebut tidak membuktikan kecocokan terhadap Kanwil.
Nilai acuan Area 6 kedua workbook sama untuk Januari–Agustus.

Contoh selisih aplikasi dikurangi acuan:

| RM / bulan | Selisih | Kuadran aplikasi / acuan |
| --- | ---: | ---: |
| Bagus / Maret | -Rp48.901.718 | 3 / 2 |
| Rona / Maret | -Rp135.862.859 | 3 / 2 |
| Zulfa / Juni | -Rp435.691.563 | 3 / 3 |
| Farid / Agustus | -Rp86.768.008 | 2 / 2 |

Lihat angka presisi dalam artefak JSON/XLSX audit; tabel di atas tidak dipakai
sebagai input perhitungan.

## Batas bukti

Pergantian pemilihan rekening lama (terkecil, terbesar, seluruh saldo, rekening
paling baru, kesamaan RM/uker, serta waktu posisi saldo) sudah dibandingkan
lintas bulan. Belum ada aturan yang terbukti memenuhi target 1% dan kuadran
identik. Alternatif yang memperbaiki sebagian RM dapat memperburuk RM lain.
Karena itu tidak diterapkan koreksi persentase, konstanta per RM, maupun
pergeseran batas kuadran.

Arsip Januari–Juli hanya memiliki posisi akhir bulan. Posisi Agustus lebih
lengkap tetapi belum berisi identitas pasangan fasilitas/acuan transaksi
Kanwil. Kesamaan jumlah rekening tidak membuktikan kesamaan rekening yang
dikreditkan ke RM. Contoh Novan Agustus: total gross fasilitas yang teratribusi
di nominatif tersedia Rp3.516 juta, sedangkan net acuan Rp3.697,183325 juta;
identitas atau cakupan event perlu direkonsiliasi sebelum memilih OS pengurang.

Kedua workbook merupakan benchmark Briguna. Untuk membuktikan aturan sisa
selisih Briguna dibutuhkan rekonsiliasi identitas rekening baru, rekening lama
yang dilunasi, OS pelunasan, tanggal event, dan RM pemrakarsa. Pengujian tambahan
atribusi pengelola pertama/terakhir, klasifikasi berdasarkan rekening yang
benar-benar terganti, serta koreksi plafon Agustus pada posisi September juga
tidak menghasilkan aturan yang memenuhi batas 1%. Rumus Briguna tidak diganti
dengan koreksi persentase atau nilai Excel.

## KPR: perbaikan v27 dan verifikasi langsung

Acuan terpisah tersedia di `Time Series KPR Madiun Jan - Agt 2026.xlsx`.
Pada cutoff 31 Agustus 2026, rumus berikut diuji langsung terhadap Daily Loan:

- Realisasi KPR = jumlah plafon maksimum per nomor rekening unik yang tanggal
  realisasinya berada dalam bulan bersangkutan. Seluruh posisi dalam bulan itu
  dibaca, bukan hanya posisi akhir bulan. OS fasilitas sebelumnya tidak
  mengurangi realisasi KPR; pengurangan tersebut tetap berlaku untuk Briguna.
- Nomor rekening tetap satu fasilitas walau format nol depan/CIF berubah.
- RM KPR ditentukan per rekening dari pengelola terakhir sampai cutoff laporan.
  Tidak ada alias PN lama ke PN baru yang di-hardcode. Pemecahan portofolio ke
  beberapa RM tidak dianggap sebagai pemindahan semua rekening satu PN.
- Snapshot bulanan menyimpan atribusi pada cutoff bulan itu. KPI dan landing
  memakai proyeksi bersama untuk atribusi realisasi historis pada cutoff laporan.
  Proyeksi hanya memindahkan realisasi, bukan nilai OS/LAR portofolio.

Hasil audit `storage/app/consumer-kpr-verified-20260909-211756/`:

| Pemeriksaan | Cocok | Selisih |
| --- | ---: | ---: |
| Daily Loan terhadap sel KPR/KPRS Excel | 48/48 | Rp0 (toleransi float < Rp1) |
| Kalkulator aplikasi terhadap rincian rekening | 48/48 | Rp0 (toleransi float < Rp1) |
| Controller KPI, total per RM/bulan | 24/24 | Rp0 (toleransi float < Rp1) |
| Proyeksi realisasi landing, total per RM/bulan | 24/24 | Rp0 (toleransi float < Rp1) |

Total KPRS Rp32.895.020.000 dan KPR komersial Rp17.118.248.000; gabungan
Rp50.013.268.000. Contoh Taufik Februari berubah dari pengurangan net
Rp1.542.921.416 menjadi booked plafond Rp2.106.220.000 sesuai acuan.

Snapshot akhir bulan Januari sampai Agustus telah dibangun ulang memakai
command incremental `snapshot:rebuild-rm --period=...`; tidak menjalankan force
delete. Pemeriksaan pascarebuild: 83 kelompok snapshot KPR cocok dengan
kalkulator, tanpa selisih. Cache pinjaman diperbarui oleh command rebuild.
Audit ulang Briguna berada di
`storage/app/consumer-precision-audit-20260909-212130/rekonsiliasi-jan-agt-2026.xlsx`;
hasilnya tetap 81/96 kuadran cocok, bukan lulus target 1%.

**Batas validasi:** workbook KPR ini tidak memuat target atau kuadran, maupun
pemisahan baru/suplesi. Database belum memiliki target Taufik; target Abdul
Rp3,5 miliar/bulan masih terdaftar dan BRIHC masih menandai keduanya sebagai RM
KPR. Target tidak otomatis dipindahkan hanya karena rekening berpindah.
Karena itu kecocokan nominal KPR tidak dinyatakan sebagai bukti kecocokan
seluruh kuadran. Pemisahan baru/suplesi KPR tetap berbasis riwayat CIF produk;
belum memiliki pembanding independen. Kebutuhan baseline akhir bulan sebelumnya
untuk klasifikasi tersebut tidak diubah.

Skrip lokal `storage/app/consumer-kpr-source-probe.php` mengulang pembandingan
raw Daily Loan, kalkulator, controller KPI, dan proyeksi landing serta menulis
JSON/XLSX baru. Workbook hanya dibaca oleh audit; aplikasi tetap membaca Daily
Loan/arsip posisi Daily Loan, bukan angka Excel.

## Briguna: bukti posisi harian dan perbaikan v28

Pemeriksaan lanjutan pada 9 September menemukan cache tautan eksternal workbook
RO ke `07. Realisasi Harian RM Briguna Juli 2026.xlsx`, sheet `RM`. Cache ini
menyimpan 372 baris pembanding RM/tanggal untuk Area 6. Kolom N:LK berisi 31
blok harian (10 kolom per tanggal), sedangkan LL:LO menyimpan total baru/suplesi.
Nilai hanya dibaca dari ZIP workbook, tanpa menjalankan tautan atau formula.

Salinan staging Daily Loan posisi 21 dan 25 Juli juga masih tersedia di
`storage/app/temp/daily_loan_polars_627aab58-4481-4258-946a-48b58ab5f5db.csv`
dan `daily_loan_polars_6fcead17-71ac-44ac-87d6-b2b9d99570c2.csv`. Keduanya hanya
dibaca untuk rekonsiliasi dan belum dimasukkan kembali ke tabel sumber/arsip.

Perubahan OS gabungan CIF pada tanggal pencairan terhadap akhir bulan
sebelumnya cocok tepat untuk 23/24 pasangan RM/tanggal; error absolut
tertimbang 0,0991008%. Delapan pasangan bernilai nol. Pengecualian Ardini
21 Juli: sumber Rp20 juta, acuan Rp17.467.278. Jadi angka tertimbang tersebut
tidak berarti semua RM memenuhi 1%.

Contoh acuan yang menjelaskan perbaikan:

- Zulfa, 25 Juli: OS rekening baru Rp280 juta + OS rekening lama yang masih
  terbuka Rp254.068.779 - OS akhir Juni Rp255.851.152 = Rp278.217.627.
  Membaca hanya akhir Juli menghilangkan rekening lama dan menghasilkan
  perkiraan Rp24.148.848.
- Dimas, 25 Juli: Rp510 juta - dua fasilitas lama (Rp390.155.278 dan
  Rp96.115.274) = Rp23.729.448. Mengurangi satu fasilitas saja terlalu besar.
- Bagus, 31 Juli: Rp100 juta + pencairan sebelumnya Rp350 juta + sisa rekening
  lama Rp227.595.240 - baseline Rp228.203.372 = Rp449.391.868 untuk event itu.

Kalkulator v28 memakai perubahan OS CIF **dalam produk Briguna Konsumer** jika
posisi tanggal realisasi tersedia, hanya satu rekening dicairkan pada CIF/tanggal
itu, dan OS rekening baru masih sama dengan plafonnya. Ini membatasi penerapan
pada kondisi yang telah direkonsiliasi. Jika tidak, perhitungan fasilitas lama
tetap menjadi estimasi. Belum ada dasar untuk mengalokasikan perubahan CIF
ke beberapa pencairan pada hari yang sama. KPR tetap memakai plafon bruto.

Pengujian regresi menggunakan nominal independen di atas dengan identitas
rekening sintetis; juga menguji arsip posisi, pengulangan pencairan satu CIF,
tanggal pencairan yang tidak tersedia, dan pencegahan hitung ganda pada
beberapa pencairan satu hari. Nilai workbook tidak dipakai oleh aplikasi.

Audit kalkulator v28 terhadap 96 RM/bulan:

| Bulan 2026 | Kuadran cocok | Error nominal tertimbang |
| --- | ---: | ---: |
| Januari | 12/12 | 3,0811% |
| Februari | 12/12 | 1,8863% |
| Maret | 9/12 | 3,5347% |
| April | 12/12 | 4,4368% |
| Mei | 11/12 | 5,5500% |
| Juni | 8/12 | 5,9084% |
| Juli | 8/12 | 6,6753% |
| Agustus | 10/12 | 2,3160% |

Kuadran cocok 82/96. Novan Agustus menjadi Rp3.699.182.294 terhadap acuan
Rp3.697.183.325 (error 0,0541%), sehingga kuadran 2 sesuai acuan. Perbaikan
ini belum memenuhi target 1% seluruh RM/bulan; sejumlah sel justru membesar
selisihnya, misalnya Farid Agustus. Riwayat tanggal pencairan dan baseline
yang lengkap masih perlu direkonsiliasi sebelum mengganti estimasi sisanya.

Artefak penelusuran berada pada `storage/app/consumer-july-exact-date-analysis.json`,
`consumer-july-account-analysis.json`, dan `consumer-cif-movement-analysis.json`.
Skrip `consumer-july-exact-date-probe.php` dan `consumer-cif-movement-probe.php`
di folder yang sama mengulang pemeriksaan dengan bahan tersebut.

### Konsistensi halaman dan validasi setelah rebuild

Audit render menemukan Abdul masih termasuk roster aktif BRIHC dan dihitung
landing sebagai kuadran 4, tetapi KPI menghapus barisnya karena tidak ada
realisasi dua bulan terakhir. KPI kini mempertahankan RM Konsumer aktif BRIHC
meski realisasinya nol; RM yang telah pindah jabatan tetap mengikuti filter
yang ada. Target baru untuk Taufik tidak ditebak atau diwariskan otomatis.

Snapshot Januari–Agustus dan 7 September dibangun ulang secara incremental.
Audit `storage/app/consumer-precision-audit-20260909-215915/` menunjukkan seluruh
96 total snapshot cocok kalkulator v28. Audit ulang KPR
`storage/app/consumer-kpr-verified-20260909-215950/` tetap cocok 48/48 sel sumber
dan kalkulator, serta 24/24 total bulanan pada masing-masing KPI dan landing.

Audit `storage/app/consumer-pages-verified-20260909-215858/` membandingkan dua
arah seluruh identitas RM/bulan kedua halaman: 128/128 kuadran konsisten,
tanpa RM tambahan di landing. Tabel KPI Briguna/KPR dan partial landing berhasil
dirender dari data produksi. Ini verifikasi server/render HTML, belum pengujian
interaksi browser. Konsistensi mencakup nilai kuadran kosong ketika target
belum tersedia; bukan pengesahan target/kuadran Taufik terhadap Kanwil.

Validasi kode: 87 pengujian terkait lulus (490 assertions), Vite build, kompilasi
Blade, syntax PHP, dan pemeriksaan diff lulus. Pint lulus untuk kalkulator,
signature, dan kedua test yang diubah. Controller KPI masih memiliki lima jenis
pelanggaran format yang juga ditemukan pada versi HEAD; tidak dilakukan
format ulang seluruh controller.

### Simulasi pemulihan posisi Juli

`storage/app/consumer-july-restoration-simulation.php` menyalin arsip akhir Juni
dan Juli serta roster ke SQLite dalam memori. Hasil awal simulasi diperiksa
sama persis dengan kalkulator database aplikasi sebelum menambahkan data CSV.
File 21 Juli berisi 320.124 baris (17.030 rekening Konsumer unik); 25 Juli
320.524 baris (17.040 rekening Konsumer unik). Semua baris diperiksa periodenya.
Database aplikasi tetap hanya dibaca; manifest tambahan hanya berlaku dalam
simulasi dan tidak dinyatakan sebagai hasil capture produksi.

Hasil simulasi: error nominal Juli turun dari 6,6753% ke 5,2472%; kuadran cocok
naik dari 8/12 ke 9/12. Zulfa menjadi kuadran 1 sesuai acuan. Sebagian RM tetap
berbeda atau memburuk, sehingga pemulihan dua tanggal ini belum cukup untuk
menyelesaikan rekonsiliasi. Total produksi tetap mengikuti tabel v28 di atas.
Rincian tersimpan di `storage/app/consumer-july-restoration-simulation.json`.

Alternatif klasifikasi pencairan berikutnya pada CIF yang sama dalam satu bulan
juga diuji terpisah. Pada Agustus, kecocokan jumlah rekening baru turun dari
6/12 menjadi 5/12; aturan tersebut tidak diterapkan. Hasil tersedia pada
`storage/app/consumer-repeat-booking-analysis.json`.

Daftar minimal tanggal posisi yang masih hilang disimpan dalam
`storage/app/consumer-source-gaps-2026.json`. Daftar dibentuk dari tanggal
pencairan yang masih terlihat di arsip dan acuan harian Juli; rekening yang
sudah hilang sebelum posisi pertama dapat menambah kebutuhan data. Dibutuhkan
riwayat Daily Loan Dinamis/LW321PN harian dan, untuk selisih yang tetap ada pada
tanggal lengkap, rincian transaksi/pasangan rekening versi Kanwil. Target KPR
Taufik beserta periode berlakunya juga masih memerlukan acuan pengguna.

### Pemulihan database 10 September 2026

Atas instruksi pengguna, data asli dari dua CSV staging dipulihkan ke
`consumer_rm_position_history` dan manifest `consumer_rm_position_captures`:

| Posisi | Baris CSV diperiksa | Rekening Konsumer dipulihkan |
| --- | ---: | ---: |
| 21 Juli 2026 | 320.124 | 17.030 |
| 25 Juli 2026 | 320.524 | 17.040 |

Skrip `storage/app/restore-consumer-july-positions.php` terlebih dahulu memproses
CSV lengkap dalam SQLite memori menggunakan normalisasi/deduplikasi milik
`ConsumerRmPositionHistoryStore`. Kolom status dan restrukturisasi turut
dipertahankan. Posisi, format, jumlah baris, dan SHA-256 file diperiksa; hash
isi seluruh baris yang dibaca kembali dari MySQL harus identik sebelum commit.
Kedua posisi disimpan dalam satu transaksi dengan lock capture. Data yang
sudah ada tidak ditimpa; pengulangan pemulihan telah diuji ditolak dengan exit 1.

Hasil per RM Juli diperiksa terhadap seluruh nominal dan kuadran simulasi di
dalam transaksi. Perhitungan KPR Juli juga wajib tetap sama. Bukti asal sumber,
baris pemulihan, kondisi snapshot sebelum perubahan, dan hasil commit ada di
`storage/app/consumer-july-recovery-20260910-072952/`. Ini arsip posisi dari
Daily Loan asli, bukan data sintetis tes atau nilai realisasi dari workbook.

Command incremental `snapshot:rebuild-rm --period=2026-07-31` berhasil membangun
1.464 baris dan memperbarui versi cache pinjaman. Rekonsiliasi ulang
`storage/app/consumer-precision-audit-20260910-073133/` membuktikan seluruh
96 total snapshot sesuai kalkulator; bulan selain Juli tidak berubah. Kuadran
Juli cocok 9/12, total Januari-Agustus 83/96. Error Juli 5,2471847645%.

Audit halaman `storage/app/consumer-pages-verified-20260910-073117/` kembali
lulus 128/128 perbandingan KPI-landing dan render HTML. Pengujian terkait
lulus 87 tes / 490 assertions. Tidak ada perubahan rumus, schema, atau UI pada
tahap pemulihan ini; build frontend tidak diulang untuk perubahan data saja.
Audit KPR `storage/app/consumer-kpr-verified-20260910-073210/` tetap cocok
48/48 angka sumber/kalkulator dan 24/24 total pada masing-masing KPI/landing.
Target kuadran KPR Taufik masih belum terverifikasi terhadap acuan independen.

### Audit lanjutan 14 September 2026: sumber selisih Briguna

Audit read-only posisi 12 September membandingkan kalkulator, snapshot KPI,
dan baris realisasi landing. Keduanya menghasilkan Briguna **169 rekening /
Rp16.512.174.886**, sedangkan tabel Kanwil 12 RM yang diberikan pengguna
berjumlah **169 rekening / Rp15.930.000.000** (selisih total
Rp582.174.886). KPR pada posisi yang sama **12 rekening / Rp2.512.700.000**
di kalkulator dan KPI. Tujuh rekening Briguna senilai **Rp838.519.594**
berada pada bucket `PN BELUM TERISI`: KC Magetan satu / Rp200 juta,
KC Ponorogo lima / Rp608.519.594, dan KC Madiun KCP Caruban satu /
Rp30 juta. PN pengelola maupun pemrakarsa kosong pada nominatif dan raw
Daily Loan tanggal tersebut; jangan memindahkannya ke RM berdasarkan CIF
lama atau menebak dari selisih agregat Kanwil.

Pada Juli/Agustus, snapshot KPI yang sebelumnya dibangun dengan versi
kalkulator lama berbeda dari kalkulasi ulang versi `consumer-cif-event-v2`:
Juli Rp41.572.098.365 versus Rp39.892.808.867, Agustus
Rp41.703.643.110 versus Rp41.044.158.804 (jumlah rekening masing-masing
tetap 386 dan 369). Pada 11 RM dengan referensi Kanwil Juli/Agustus,
jumlah selisih absolut snapshot masing-masing Rp2.161.888.738 dan
Rp968.016.078; kalkulasi ulang v2 masing-masing Rp3.012.252.855 dan
Rp1.548.990.903. Jadi membangun ulang seluruh bulan historis dengan v2
atau memaksa landing dan KPI memakai v2 tanpa audit nominatif justru
memperbesar selisih terukur. Percobaan menjumlahkan *kenaikan inkremental*
untuk beberapa tanggal pencairan CIF juga diuji dan dibatalkan karena
memperbesar selisih RM terhadap referensi Kanwil.

Pengujian ulang independen KPR Januari–Agustus pada
`storage/app/consumer-kpr-verified-20260914-164651/` lulus 48/48 sel
plafon bruto sumber/kalkulator serta 24/24 total bulanan masing-masing
di KPI dan proyeksi landing. Tidak ada dasar untuk mengubah rumus KPR.
Untuk merekonsiliasi angka RM Briguna hingga nominal tepat diperlukan
nominatif Kanwil per rekening/CIF beserta tanggal pencairan dan PN yang
diatribusikan (khususnya tujuh rekening PN kosong), serta riwayat posisi
Daily Loan untuk semua tanggal tersebut. Angka ringkasan RM tidak cukup
untuk membedakan kesalahan atribusi PN dari perbedaan aturan suplesi.

Validasi yang tidak mengubah angka produksi: 74 pengujian terkait
(424 assertions) lulus, Vite build lulus, dan render server KPI Briguna,
KPR serta partial landing lulus. Audit kuadran historis 128/128 cocok
(`storage/app/consumer-pages-verified-20260914-164742/`); kecocokan
kuadran **bukan** bukti nominal historis cocok karena perbedaan snapshot
dan kalkulator v2 di atas masih ada. Tidak dilakukan rebuild historis
atau atribusi otomatis PN tanpa nominatif pembanding.

### Audit Ridho dan uji sensitivitas tanpa nominatif Kanwil

Pada 12 September, sumber asli dan arsip sama-sama memuat **12 booking
Briguna di KCP Caruban**. Sebelas rekening ber-PN Ridho menghasilkan
**Rp1.255.908.067** menurut perubahan CIF pada posisi pertama yang tersedia:

| CIF | Akhir Agustus | OS CIF saat event | Kontribusi | Klasifikasi |
| --- | ---: | ---: | ---: | --- |
| HK24258 | Rp176.801.265 | Rp426.801.265 | Rp250.000.000 | Suplesi |
| TK33040 | Rp6.438.746 | Rp81.438.746 | Rp75.000.000 | Suplesi |
| E187433 | Rp0 | Rp130.000.000 | Rp130.000.000 | Baru |
| UA53133 | Rp88.598.459 | Rp102.598.459 | Rp14.000.000 | Suplesi |
| KJ90714 | Rp0 | Rp60.000.000 | Rp60.000.000 | Baru |
| SHR7864 | Rp46.274.801 | Rp61.000.000 | Rp14.725.199 | Suplesi |
| AOM1677 | Rp0 | Rp35.000.000 | Rp35.000.000 | Baru |
| THJ1062 | Rp100.201.704 | Rp138.000.000 | Rp37.798.296 | Suplesi |
| SAOLY72 | Rp0 | Rp325.000.000 | Rp325.000.000 | Baru |
| WF31857 | Rp0 | Rp300.000.000 | Rp300.000.000 | Baru |
| KGY8641 | Rp87.134.726 | Rp101.519.298 | Rp14.384.572 | Suplesi |

Rekening kedua belas, `55201008431107`/CIF `SVB6026`, bernilai Rp30 juta,
tetapi PN pengelola dan pemrakarsa kosong di raw Daily Loan maupun arsip.
Mengaitkannya ke Ridho hanya berdasarkan unit mengubah hasil menjadi
12 rekening/Rp1.285.908.067, *lebih jauh* dari angka PPT 12/Rp1.110 juta.
Kolom harian PPT 11 September (2 rekening/Rp315 juta) cocok dengan **plafon
bruto** dua booking bertanggal realisasi 11 September, Rp300 juta + Rp15 juta;
ini bukan bukti bahwa angka kumulatif memakai rumus bruto yang sama.

Pada CIF `HK24258`, rekening lama Rp176.801.265 masih ada pada posisi event
1 September dan hilang mulai 2 September. Mengganti posisi event dengan
posisi akhir 12 September menurunkan kontribusi CIF itu dari Rp250 juta ke
Rp73.198.735. Namun aturan cutoff untuk semua CIF bukan koreksi yang aman:
Zulfa memiliki dua pencairan dalam CIF `RP47870` (2 dan 9 September), dan
simulasi cutoff penuh menurunkan nilai Zulfa sekitar Rp335 juta padahal
hasil event saat ini hanya selisih Rp182.433 dari PPT. Jumlah selisih absolut
12 RM naik dari **Rp1.361.225.064** menjadi **Rp1.608.245.478** jika seluruh
CIF dipaksa memakai cutoff. Aturan cutoff hanya untuk CIF satu booking
menurunkan selisih absolut menjadi Rp1.273.245.478, tetapi memperburuk
7 RM dan memperbesar kekurangan agregat RM terhadap PPT. Kedua variasi
ditolak; definisi dan implementasi produksi tidak diubah dari hasil audit ini.

Tanggal posisi 3 dan 10 September tidak tersedia pada sumber; dua booking
Ridho bertanggal tersebut pertama terlihat masing-masing 4 dan 11 September.
Keduanya CIF baru, sehingga tetap dihitung dari plafon bruto dan pergeseran
posisi tidak menjelaskan selisih Ridho. Tidak ditemukan duplikasi booking
atau selisih saldo CIF antara arsip dan raw untuk 12 rekening Caruban pada
baseline/event yang diperiksa. Dengan PPT agregat saja, nilai pasti tiap
suplesi dan pemilik PN kosong tidak dapat diidentifikasi secara unik.
