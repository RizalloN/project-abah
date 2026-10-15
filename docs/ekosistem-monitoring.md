# Monitoring Ekosistem

Route `/ekosistem` menampilkan monitoring dari spreadsheet `1bssXQGe2PVUQYuNQVfiXuo5TDLuidO6TeU1cYR9iHZI`, tab **Ekosistem**, **Prospek**, dan **Log**. Pembacaan tetap melalui sumber dan cache controller; halaman ini tidak menyediakan tambah, edit, atau import prospek.

## Susunan tampilan

- **Ringkasan:** potensi menurut jenis, polygon wilayah, eksekusi, perbandingan KC, prioritas tindak lanjut, dan aktivitas terakhir. Pada ponsel, angka operasional muncul sebelum kontrol.
- **KC & PIC:** perbandingan KC, matriks jalur RM, dan kelompok PIC dengan pagination 20 kelompok.
- **Ekosistem & ring:** cakupan L1–L3, profil ekosistem, serta informasi prospek yang belum terhubung ke master.
- **Detail prospek:** 20 baris per halaman; dialog menampilkan identitas, PIC, potensi, progres, dan catatan sumber.

Ringkasan dan detail memakai filter server yang sama. Parameter filter dipertahankan ketika berpindah tab, halaman, atau membuka rincian. Perbandingan antar-KC mengabaikan pilihan KC aktif, mengikuti pola sumber; label menjelaskan konteks ini. Hak akses KC tetap membatasi seluruh data, geometri, pilihan filter, log, dan payload dialog.

## Angka dan kondisi

`EkosistemMonitoring` mengikuti fungsi `allStats()` dan urutan `health()` dari Index.html yang diberikan pengguna:

- Estimasi simpanan dan kredit dijumlahkan terpisah, masing-masing minimum dan maksimum. Maksimum kosong mengikuti minimum.
- Arus dana adalah estimasi per tahun; tidak digabung menjadi saldo bersama simpanan/kredit. Payroll menjumlahkan estimasi orang hanya pada jenis Payroll.
- Realisasi nominal dan nasabah menjumlahkan nilai input sumber. Nilai nol tetap ditampilkan.
- Cakupan PIC menghitung semua nama PIC yang tidak kosong. **PIC belum diisi** adalah total dikurangi PIC terisi. **Prioritas tanpa PIC** merupakan kondisi sumber setelah pemeriksaan status dan prasyarat, sehingga jumlahnya dapat berbeda.
- Kondisi mengikuti urutan: selesai/batal, lewat target, tertunda, berjalan mandek >14 hari, berjalan jatuh tempo ≤7 hari, berjalan sesuai jadwal, menunggu prasyarat, tanpa PIC, lalu siap dimulai.

Penjagaan satuan yang sudah ada tetap berlaku: nominal per prospek ≥10.000.000 juta (Rp10 T) ditandai dan dikeluarkan dari rekap nominal. Nilai asli tersedia pada rincian peringatan; spreadsheet tidak diubah. Rekonsiliasi dengan fungsi sumber dilakukan setelah penjagaan ini.

Jumlah ekosistem terdaftar menggunakan ID master. Prospek tanpa hubungan master tetap masuk total dan dapat difilter secara khusus; kelompok nama sumber tidak otomatis dianggap ekosistem terdaftar.

## Polygon dan aksesibilitas

`EkosistemGeography` memakai berkas administratif yang sudah tersedia di `public/data/marketshare-area6-kecamatan.geojson`, dengan pemetaan wilayah dari `config/marketshare-geography.php`. Polygon Madiun, Magetan, Ngawi, dan Ponorogo dapat dipilih untuk memfilter KC. Polygon menunjukkan cakupan administratif, bukan koordinat prospek atau nasabah. Tidak ada layanan peta/dependency baru.

Tampilan mempertahankan tema BRI/A-Six: biru navy untuk ringkasan finansial, angka tabular, panel putih, batas halus, serta status berwarna dengan label teks. Filter memakai GET, dialog native dapat ditutup dengan Escape, fokus keyboard terlihat, dan animasi mengikuti reduced motion. Nilai sumber pada dialog dimasukkan melalui `textContent`.

## Bukti validasi 15 Oktober 2026

Pembacaan sumber pukul 10:02 menghasilkan 268 prospek: Madiun 92, Magetan 48, Ngawi 70, Ponorogo 58. Ada 17 ekosistem terdaftar dan satu prospek tanpa hubungan master. PIC terisi 129, kosong 139; kondisi prioritas tanpa PIC 126. Sebanyak 15 prospek memerlukan pemeriksaan satuan.

Perbandingan seluruh 268 baris dengan fungsi JavaScript asli menghasilkan 12 komponen rekap cocok dan nol perbedaan kondisi. Ini adalah bukti pada waktu pembacaan, bukan angka yang ditanam dalam aplikasi. Hasil ringkas tersimpan di `storage/app/ekosistem-monitor-source-parity.json`.

Pengujian controller, agregasi, scope, filter, pagination, dan dialog: **18 test / 214 assertion lulus**. PHP lint, Pint terarah, kompilasi Blade, dan Vite build lulus. Pemeriksaan browser lokal memakai data sumber aktual; lebar 320, 390, 768, 1440, 1586, dan 1920 px tidak mengalami overflow halaman. Navigasi polygon, filter kualitas, pagination PIC, dan dialog diverifikasi.

Pratinjau lokal tersimpan di `storage/app/ekosistem-monitor-desktop.png` dan `storage/app/ekosistem-monitor-mobile.png`. Browser produksi dengan akun terautentikasi belum diverifikasi; pratinjau menggunakan identitas lokal sementara dan tidak menulis ke sumber.
