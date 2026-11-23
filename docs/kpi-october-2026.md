# KPI posisi 31 Oktober 2026

Empat workbook sumber ditambahkan sebagai snapshot Oktober. Sumber lama tidak diganti atau dihapus. Filter periode membuka Oktober untuk empat KPI berikut; September, Juli, dan Juni tetap tersedia sesuai sumber masing-masing. KPI Mantri Briguna tetap membuka September; KPI SME dan Konsumer tidak berubah.

| Menu/key | Workbook | Worksheet | Profil | Kolom | Sel |
|---|---|---|---:|---:|---:|
| KPI MBM / `mbm` | KPI Mbm.xlsx | KPI MBM | 12 | 24 | 288 |
| KPI KUR Kecil / `rm-mikro` | KPI RM Kur Kecil.xlsx | KPI RM Mikro | 14 | 32 | 448 |
| KPI KA Unit / `ka-unit` | KPI Kaunit.xlsx | KPI Kaunit | 101 | 28 | 2.828 |
| KPI Mantri / `mantri` | KPI Mantri Tumbuh.xlsx | KPI Mantri Tumbuh | 466 | 44 | 20.504 |

Snapshot `resources/data/kpi-<key>-2026-10.json` menyimpan SHA-256 sumber, worksheet, koordinat baris/kolom, nilai mentah, format angka, hasil tampilan, serta formula asli. Seluruh kolom, termasuk pemisah kosong pada KPI RM Mikro, dipertahankan. Metadata cabang dipakai untuk verifikasi/filter dan tidak menulis ulang nilai sel sumber. Snapshot tidak dipakai untuk mengubah roster/personnel atau sumber produktivitas.

Formula tidak dihitung ulang. Ada 656 formula Mantri dengan hasil teks kosong yang tersimpan sah dalam XML Excel; hasil ini tetap kosong dan tidak diubah menjadi nol. Tidak ditemukan formula tanpa cache hasil maupun sel error pada profil yang diekstrak. Tampilan menggunakan placeholder `-` untuk sel kosong sesuai perilaku tabel yang sudah ada.

Sumber asli dibaca tanpa disimpan ulang. Setelah pemeriksaan MarkItDown, snapshot dapat dibangun atau diverifikasi dengan:

```powershell
python scripts/build-kpi-october-reference.py <direktori-sumber>
python scripts/build-kpi-october-reference.py <direktori-sumber> --check
```

Builder hanya menulis empat snapshot Oktober. `--check` membuka ulang workbook dan memeriksa nilai, format, hasil tampilan, cakupan profil, header, formula, serta metadata kualitas tanpa menulis snapshot. Pembangunan ulang menghasilkan file identik.

Validasi 16 November 2026: 593 profil / 24.068 sel sumber cocok tanpa selisih; 24.068 sel hasil render Blade juga cocok dengan snapshot. Pengujian KPI Oktober, September, dan sumber historis lulus 31 tes / 3.099 assertions, mencakup filter cabang, scope pengguna terkunci, cache per periode, dan pencegahan sinkronisasi referensi. Pemeriksaan browser lokal menggunakan render data snapshot; sesi produksi terautentikasi belum diuji.
