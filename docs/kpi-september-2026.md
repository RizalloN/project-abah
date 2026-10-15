# KPI posisi 30 September 2026

Empat workbook pengguna menjadi snapshot KPI September. Halaman menggunakan view dan tema KPI Mantri yang sudah ada, termasuk pencarian, sorting, filter cabang, dan pembatasan akses wilayah.

| Menu / key | Worksheet sumber | Baris profil | Kolom |
|---|---|---:|---:|
| KPI Mantri / `mantri` | KPI Mantri Tumbuh | 464 | 44 |
| KPI Mantri Briguna / `mantri-briguna` | KPI Mantri Briguna | 2 | 35 |
| KPI KA Unit / `ka-unit` | KPI Kaunit | 101 | 28 |
| KPI MBM / `mbm` | KPI MBM | 12 | 24 |

Jumlah di atas adalah isi worksheet KPI, bukan jumlah roster BRIHC atau denominator produktivitas. Baris kontrol tanpa identitas tidak masuk profil. Tidak ada profil yang ditambahkan berdasarkan perkiraan. Briguna hanya mempunyai dua profil sumber: satu Madiun dan satu Magetan.

Snapshot disimpan di `resources/data/kpi-<key>-2026-09.json`. Setiap file mencatat nama dan SHA-256 workbook, nama worksheet, nomor baris/kolom Excel, nilai mentah, format angka, serta nilai tampilan. Seluruh kolom sumber dipertahankan, termasuk seri BC dan kolom kosong. Formula tidak dihitung ulang: yang digunakan adalah hasil tersimpan dalam workbook.

`scripts/build-kpi-september-reference.py <direktori-sumber>` membangun ulang keempat snapshot secara deterministik setelah pemeriksaan MarkItDown. Builder membaca sumber tanpa menulis workbook; pemformatan mengikuti PhpSpreadsheet. Persentase dan skor numerik dibedakan sesuai format setiap sel, misalnya skor MBM `99.92%` dan skor KA Unit `106.90`. Skor nol dengan format accounting tetap masuk denominator rata-rata meskipun Excel menampilkannya sebagai `-`.

Keempat menu membuka September secara default. Juni/Juli yang sudah tersedia tetap memakai sumber lama. KPI RM Mikro, RM SME, dan Konsumer tetap memakai periode/sumber masing-masing. Briguna tidak menampilkan periode historis yang belum diberikan. Tombol sumber dan badge menjelaskan bahwa September berasal dari Excel, bukan Google Sheets live.

Snapshot dibaca langsung dan tidak dapat ditimpa cache Google lama. Refresh September tidak mengantrekan pembacaan Google atau menyinkronkan roster BRIHC/wilayah MBM. Sumber snapshot ini hanya untuk laporan KPI.

Validasi pada 15 Oktober 2026:

- Semua 579 profil dan 23.602 sel dibandingkan kembali dengan workbook: nilai mentah, format, dan tampilan cocok; nol selisih.
- Seluruh 23.602 sel hasil render browser lokal cocok dengan snapshot; kolom kosong memakai placeholder `-` pada tampilan.
- 33 pengujian / 899 assertion lulus, termasuk sumber periode lama, scope cabang, angka/format, skor nol, cache usang, dan pencegahan sinkronisasi referensi.
- Browser lokal 390, 768, dan 1586 px: tidak ada overflow halaman, satu wadah scroll tabel. Pencarian, sorting skor, dan filter Magetan diverifikasi.
- PHP lint, Pint untuk file baru, kompilasi Blade, dan build Vite lulus.

Browser produksi dengan akun terautentikasi belum diverifikasi. Bukti rekonsiliasi tersedia di `storage/app/kpi-source-reconciliation.json`; pratinjau Briguna di `storage/app/kpi-briguna-september-desktop.png` dan `storage/app/kpi-briguna-september-mobile.png`.
