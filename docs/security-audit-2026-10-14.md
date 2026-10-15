# Audit keamanan ABAH — 14 Oktober 2026

Audit memakai knowledge graph, penelusuran source, reproduksi terisolasi, pengujian regresi, dan pemeriksaan runtime lokal. Lima celah telah diperbaiki. Ini bukan pernyataan bahwa seluruh aplikasi bebas kerentanan; temuan dependency dan batas pemeriksaan dicatat di bawah.

**Cakupan dan sumber bukti**

- Graph awal valid dan fresh: 666 source files; domain access-control, platform, import, dashboard-simpanan, dan presentation ditelusuri ke source aktual.
- Inventaris runtime: 317 route, 303 berautentikasi, 172 khusus admin. Route publik terbatas pada home, health, login/reset, workbook bertoken, dan integrasi Office.
- Pemeriksaan meliputi login/reset/logout, sesi, role, cabang, mutasi user, import source ownership, upload chunk, archive preflight, download root confinement, subprocess, parameter SQL yang relevan, Office JWT/URL, header/error, serta dua renderer nominatif Simpanan.
- Pengujian menggunakan SQLite per proses atau memori, temporary fixtures, dan HTTP fake. Data laporan produksi, schema, indeks, dependency, serta logika import/delete tidak diubah.
- Perubahan lokal Insentif Mantri dan graph yang sudah ada sebelum audit dipertahankan.

**Temuan yang diperbaiki**

| ID | Prioritas dan bukti sebelum patch | Perbaikan | Bukti sesudah patch |
| --- | --- | --- | --- |
| S01 | Tinggi: sesi hanya mengikat ID user. Pada driver file, hapus user lalu gunakan kembali ID dapat membuat sesi lama mengenali akun pengganti, termasuk admin. Sesi setelah perubahan password juga belum diverifikasi terhadap password sekarang. | Fingerprint dari ID, PN, role, cabang, password hash, dan created_at; verifikasi sebelum scope dan pelepasan sesi. Remember cookie diverifikasi melalui middleware Laravel sebelum konteks dibuat. | Real SessionGuard memuat ulang user dari SQLite: ID reuse, perubahan password/PN/role/cabang, serta sesi ID-only ditolak. Login dan remember valid tetap bekerja. Ubah password sendiri mempertahankan perangkat saat ini dan menolak sesi perangkat lain. |
| S02 | Tinggi: string spreadsheet pada dua modal Simpanan diinterpolasi ke innerHTML. Payload Partner berhasil menjalankan event handler pada browser terisolasi dengan CSP existing. | Escape nilai dinamis pada modal Perusahaan Anak dan Ekosistem; perhitungan, filter, dan layout dipertahankan. | 20 variasi field/alias berbahaya menjadi teks, tanpa elemen img/script hasil injeksi atau eksekusi. DOM teks data normal sebelum/sesudah identik; nominal dan total tetap. |
| S03 | Tinggi: user Madiun dapat meminta global_scope Ponorogo pada ekspor PPTX. Deck service mengambil nominal sentinel cabang lain dari snapshot sintetis. | Empat scope ekspor dibatasi sesuai akun sebelum baca payload atau antrekan job. Selector Blade dan pengisian ulang JavaScript menampilkan cabang yang diizinkan. | Kedua endpoint ekspor menolak empat field lintas cabang/Area 6 dengan 422. Scope sendiri diterima; akun Area 6 mempertahankan seluruh pilihan. |
| S04 | Tinggi untuk availability, memerlukan upload admin: deteksi format memuat metadata XLSX terkompresi penuh sebelum parser. | statName memeriksa keberadaan/ukuran nonkosong; hanya satu byte setiap metadata dibaca, seperti probe worksheet existing. Tidak ada batas ukuran metadata baru yang menolak workbook sah. | XLSX valid bermetadata lebih dari 8 MiB diterima detector dan reader, dengan tambahan peak detector 68.312 byte. Metadata hilang/kosong tetap ditolak. Pengurangan alokasi detector ini tidak memperbaiki risiko parser downstream. |
| S05 | Sedang: exception yang keluar dari pipeline melewati penerapan header CSP/nosniff. | Finalisasi exception memakai penerapan header existing. Penentuan no-store memakai keadaan guard/sesi tanpa lookup user ke database; respons error mendapat no-store. | Respons 403/404/419/500 mempertahankan header. RuntimeException 500 tidak menampilkan detail internal. Pengujian header pada database failure tidak menambah lookup user. |

Untuk S01, runtime yang diperiksa saat final memakai driver **database**, bukan file. Pencabutan sesi database pada administrasi user sudah tersedia sebelumnya; reproduksi pengambilalihan ID menunjukkan celah pada driver file yang didukung/default konfigurasi, bukan bukti pengambilalihan akun pada runtime saat audit. Verifikasi konteks baru melindungi driver yang didukung tanpa memindai seluruh penyimpanan sesi.

**Biaya memori dan kompatibilitas**

- Fingerprint sesi berukuran tetap 64 karakter. Middleware Laravel menyimpan verifikasi password pada sesi; tidak ada tabel, indeks, cache koleksi user, atau query laporan baru.
- Setelah SessionGuard memuat user, 25 pemanggilan verifikasi konteks menghasilkan nol query SQL tambahan dan peak tambahan di bawah 256 KiB pada test.
- Metadata dan worksheet XLSX masing-masing dibaca satu byte saat deteksi format, tanpa memuat isi penuh atau menambah batas ukuran metadata baru.
- MonitorRequestPerformance tetap melakukan agregasi jumlah/durasi query; tidak ditambah query log, binding SQL, atau penampungan hasil query.
- Sesi lama tanpa fingerprint perlu login ulang sekali. Workbook XLSX sah bermetadata besar tetap diterima. Angka dan formula bisnis laporan tidak diubah.
- Rujukan mekanisme sesi: [dokumentasi AuthenticateSession Laravel](https://laravel.com/docs/12.x/authentication#invalidating-sessions-on-other-devices).

**File yang diubah dalam audit ini**

| Area | File |
| --- | --- |
| Sesi | app/Support/AuthenticatedSessionContext.php; app/Http/Middleware/AuthenticateSession.php; app/Http/Controllers/Auth/AuthenticatedSessionController.php; app/Http/Controllers/Auth/PasswordController.php |
| Routing dan exception | bootstrap/app.php; routes/web.php; routes/auth.php; app/Http/Middleware/SecurityHeadersMiddleware.php |
| Ekspor presentasi | app/Http/Controllers/DashboardSimpananController.php, hanya validator ekspor; resources/views/presentation.blade.php; resources/js/presentation/pres-engine.js |
| XSS Simpanan | resources/views/dashboard/simpanan.blade.php, hanya escape dua modal |
| Deteksi spreadsheet | app/Support/SpreadsheetFileFormatDetector.php |
| Regresi | tests/Feature/AuthenticatedSessionSecurityTest.php; tests/Feature/PresentationExportSecurityTest.php; tests/Feature/SecurityExceptionResponseTest.php; tests/Unit/LandingSimpananDomSecurityTest.php; tests/Unit/SpreadsheetFileFormatDetectorTest.php |
| Isolasi test | tests/bootstrap.php, tambahan APP_ROUTES_CACHE per proses agar test tidak memakai route cache lama |
| Dokumentasi | docs/security-audit-2026-10-14.md |

**Validasi**

- Paket tes keamanan terfokus: **80 passed / 1.373 assertions**. Perintah: `php vendor/bin/pest tests/Feature/AuthenticatedSessionSecurityTest.php tests/Feature/PresentationExportSecurityTest.php tests/Feature/SecurityExceptionResponseTest.php tests/Unit/SpreadsheetFileFormatDetectorTest.php tests/Unit/LandingSimpananDomSecurityTest.php --compact`.
- Browser terisolasi menjalankan fungsi renderer aktual; 20 payload berbahaya diblokir dan output teks normal dibandingkan sebelum/sesudah. Ini tidak sama dengan verifikasi dashboard produksi menggunakan akun pengguna.
- Build `npm.cmd run build` lulus, 61 module. `node --check` lulus. PHP lint 16 file terkait lulus. Pint pada 11 file PHP terfokus lulus. `git diff --check` lulus.
- Pint terhadap tests/bootstrap.php masih meminta concat_space/not_operator_with_successor_space pada gaya existing. Source HEAD juga memiliki temuan tersebut; tidak dilakukan format global.
- Regresi lintas modul: **934 tes / 928 passed / 6 failed / 7.611 assertions**, mencakup 108 file. Seluruh suite keamanan terkait lulus. Bukti lokal: storage/framework/testing/security-regression.log, security-regression.junit.xml, dan security-regression-summary.json.
- Route/view cache diregenerasi. Pemeriksaan runtime sesudah aktivasi: **303 dari 303 route autentikasi memakai middleware sesi baru**, tanpa kegagalan urutan sebelum release.session.lock.
- HTTP lokal: `/login` 200, akses anonim `/report/dashboard-pinjaman` 302 ke login, route tidak dikenal 404. Ketiganya memiliki CSP dan nosniff. Tidak dilakukan login dengan kredensial produksi.
- Konfigurasi runtime terverifikasi tanpa mencetak secret: debug false, session encryption true, HttpOnly true, SameSite lax, disk privat tidak dilayani lewat route storage publik.

Enam kegagalan regresi diperiksa ulang: PasswordReset kekurangan tabel users pada fixture; LW325 berbeda ekspektasi indeks header; Prognosa masih mengharapkan namespace cache v2 sementara source memakai v3; presentasi mengharapkan literal lock lama yang juga tidak ada pada source HEAD. Keempatnya terulang saat dijalankan terisolasi dan tidak berasal dari diff keamanan. PH queue dan Simpanan mengalami ketergantungan schema antar-suite, lalu lulus terpisah (2 tes/11 assertion dan 38 tes/107 assertion). Tidak dilakukan perbaikan importer atau fixture yang tidak terkait.

LandingSimpananCardTest diperiksa terpisah: 19 passed / 3 failed pada ekspektasi fixture lama. Source Blade HEAD dirender di memori terhadap fixture yang sama, tanpa rollback file, dan juga gagal pada ekspektasi tersebut. Suite ini tidak dihitung ulang dalam 934 tes di atas.

Full suite seluruh repository tidak dijalankan karena terdapat tes yang membaca .env dan menarget MySQL live, operasi backup/sync/prune, atau cache bersama. Audit ini tidak menyatakan seluruh suite hijau.

**Knowledge graph sesudah patch**

Graph utama di docs/knowledge-graph telah memiliki perubahan lokal sebelum audit, sehingga tidak ditimpa. Sesudah patch, graph utama masih structurally valid tetapi stale. Salinan audit dibangun dengan builder proyek ke `storage/framework/testing/security-knowledge-graph`: valid dan fresh, **668 source files / 8.482 nodes / 33.994 edges** pada validasi lanjutan. Ringkasan runtime tersedia di `storage/framework/testing/security-runtime-summary.json`. Salinan ini tidak ditambahkan ke jalur request aplikasi.

**Validasi lanjutan: kompatibilitas impor**

Pengguna meminta kepastian bahwa hardening tidak mengulang masalah impor gagal akibat perubahan keamanan sebelumnya. Pemeriksaan lanjutan menemukan dan memperbaiki dua dampak yang konkret:

1. Batas metadata 4 MiB pada patch awal memang dapat menolak XLSX sah. Batas tersebut dihapus, lalu detector memakai stat dan probe satu byte untuk mempertahankan penerimaan workbook valid tanpa alokasi metadata penuh. Reader aktual menerima workbook valid bermetadata lebih dari 8 MiB; probe detector menambah peak 68.312 byte. Parsing downstream tetap memiliki risiko yang dicatat terpisah.
2. Dua preview sebelumnya menelan 401/403/419 sebagai hasil status kosong, sehingga polling dapat terus menunggu. Kini pemantauan berhenti, SSE ditutup, timer dibersihkan, tombol pulih, dan muncul tindakan login/muat ulang/dashboard sesuai masalah. Tidak ada POST impor ulang atau pembatalan job backend. Gangguan jaringan/500 sementara tetap menggunakan retry existing; respons force-start yang terlambat juga tidak menghidupkan pemantauan sesudah sesi berakhir.

Perubahan lanjutan hanya pada `app/Support/SpreadsheetFileFormatDetector.php`, kedua view `resources/views/import/preview.blade.php` dan `preview_excel.blade.php`, test detector, dua test baru `tests/Feature/Import/ImportSecurityCompatibilityTest.php` dan `tests/Unit/ImportSessionRecoveryUiTest.php`, serta dokumen ini. Logika mapping, formula, periode, loader, queue, dan delete report tidak diubah.

| Validasi | Hasil dan batas |
| --- | --- |
| HTTP impor aktual dengan sesi terenkripsi dan CSRF aktif | **16 tes / 313 assertions lulus**. Login POST dan cookie response nyata, guard/session dibangun ulang tiap request. Admin membuka halaman impor, upload, preview, init, stream, dan status. |
| Impor sampai data tersimpan | CSV dua baris benar-benar dimasukkan ke SQLite; nama cabang/unit, periode, serta saldo 1250,50 dan 2000,25 sesuai sumber. Status terminal **completed / 2 success / 0 failed / 100%**. Hanya materialisasi snapshot/downstream eksternal dimock. |
| Format dan upload | CSV, TXT, XLS, XLSX, XLSX bernama .xls, serta XLSX sah bermetadata lebih dari 4 MiB melewati upload dan preview. Native XLSX masih menghitung header pada dimensi; fallback XLS masih menunda total sampai eksekusi, sesuai behavior existing. |
| Chunk dan antrean | CSV/XLSX chunk digabung byte-identical, preview dua baris benar. Sesi fresh/remember dapat membuat state job dengan owner/path benar dan tepat satu dispatch RunImportJob. Worker antrean pada kasus ini difake; tidak disebut impor selesai. |
| Kontrol negatif | Token CSRF hilang mendapat 419; retry dengan token sah tetap berhasil. Guest/nonadmin ditolak. Admin lain tidak dapat mengambil source/chunk pemilik, sedangkan pemilik tetap dapat melanjutkan. Perubahan role mencabut akses tanpa menghapus file atau membuat job baru. |
| UI JavaScript aktual | **33 tes / 303 assertions lulus**: penghentian polling/SSE pada 401/403/419, pemulihan tombol, tindakan login/reload/dashboard, penolakan late success, pending force-start, serta retry sementara sampai terminal. Browser DOM terisolasi juga memeriksa enam variasi sesi ditolak dan dua kontrol positif jaringan. |
| Detector | **7 tes / 35 assertions lulus**, metadata/worksheet besar diperiksa dengan memori kecil; metadata kosong/hilang tetap ditolak dan XLS lama tetap dikenali. |
| Regresi existing per file | **272 tes / 1.877 assertions lulus** pada 21 suite terkait. Mencakup Daily Loan, LW321PN, GI405, RKA, Simpanan MultiPN, PH/Cognos, BRIMo, CASA BRILink, Performance PIS, preview/chunk/fallback, period guard, progress, dan autentikasi. Dijalankan per file untuk menghindari ketergantungan schema antar-suite yang telah ditemukan. |
| Jumlah validasi lanjutan | **328 tes / 2.528 assertions lulus**, tanpa kegagalan pada kumpulan ini. Bukti: `storage/framework/testing/import-security-final.xml`, `import-security-final.log`, dan `import-compat-existing-summary.json`. |
| Build dan aktivasi | npm build lulus (61 module), PHP lint dan Pint terfokus lulus, diff check bersih. Blade cache diregenerasi; runtime tetap 303/303 route autentikasi terlindungi dengan urutan benar. |

Validasi ini menggunakan fixture dan database uji terisolasi, bukan mengunggah atau mengubah laporan produksi. Seluruh kombinasi workbook besar, MySQL LOAD DATA produksi, native worker, dan browser login produksi belum dijalankan. Sesi legacy tanpa fingerprint tetap perlu login ulang sekali; preview/chunk yang masih bergantung pada sesi tersebut mungkin perlu upload ulang. Job yang telah masuk antrean/running tidak dibatalkan oleh middleware.

**Temuan tersisa dan tindak lanjut**

| Prioritas | Temuan dan batas bukti | Tindak lanjut |
| --- | --- | --- |
| Tinggi | Composer audit melaporkan 49 entri advisory pada 13 paket; termasuk PhpSpreadsheet 5.5.0, Laravel 12.55.1, Guzzle 7.10.0, dan Symfony 7.4.7. Sebagian advisory duplikat atau membutuhkan jalur penggunaan tertentu. Ini bukan 49 exploit aplikasi yang sudah dibuktikan. | Pembaruan dependency terpisah, terarah, dengan audit ulang dan regresi import/output. Dependency/lockfile tidak diubah karena aturan proyek meminta instruksi eksplisit. |
| Tinggi | PhpSpreadsheet XLS/OLE sector-chain loop dapat menghabiskan memori pada parser XLS. Pembatasan metadata ZIP S04 tidak memperbaiki parser OLE. [Advisory pemelihara](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-xh5m-36r6-47m3) mencantumkan perbaikan 5.8.1 pada lini 5.x. Tidak menjalankan fixture yang mematikan proses/server. | Prioritaskan paket spreadsheet yang kompatibel dan lolos audit ulang; jangan menganggap guard header OLE sebagai perbaikan sector-chain parser. |
| Perlu pemetaan reachability | Advisory [IOFactory filename wrappers](https://github.com/PHPOffice/PhpSpreadsheet/security/advisories/GHSA-q4q6-r8wh-5cgh) berlaku pada versi terpasang. Jalur impor yang diperiksa sudah memakai realpath/root/session ownership, sehingga SSRF/RCE dari filename tidak terbukti pada jalur tersebut. | Pertahankan guard path dan perbarui paket; telusuri semua pemanggil parser jika ada jalur baru. |
| Campuran produksi/tooling | npm audit: 18 paket terkait advisory, 2 critical/12 high/3 moderate/1 low. Termasuk axios 1.13.6, vite 7.3.1, concurrently 9.2.1/shell-quote 1.8.3. Label devDependency tidak berarti semua kode tidak dikirim ke browser: axios masuk bundle, sedangkan Vite/concurrently terutama tooling. | Pisahkan pembaruan runtime bundle dan tooling; jangan menyatakan semua advisory npm dapat dieksploitasi melalui aplikasi web. |
| Sedang | Preflight RAR memakai output `7z l -slt` yang dibuffer penuh sebelum jumlah entry dibatasi di AuthorizesImportSourceFiles. Sudah ada timeout, batas file/expanded bytes, serta pemeriksaan traversal/link, tetapi output listing sendiri belum dibatasi. | Streaming atau cap listing dengan regresi 7-Zip lintas platform. Tidak diubah dalam patch ini untuk menjaga alur ekstraksi existing. |
| Kerahasiaan log | Logger QueryException masih dapat menyimpan SQL binding melalui getMessage dan query URL melalui fullUrl. Tidak terbukti sebagai pengungkapan anonim melalui HTTP. | Redaksi log terarah dan kebijakan retensi setelah kebutuhan diagnosa disepakati; jangan menambah full query logging ke middleware performa. |
| Coverage | CSP unsafe-inline dipertahankan agar UI existing tetap berjalan. Audit DOM lintas seluruh view, concurrency/session multi-node, seluruh workbook produksi, dan pentest eksternal belum dilakukan. | Audit lanjutan berdasarkan jalur yang reachable, dengan pembandingan output produksi dan profiling terukur. |

Bukti audit dependency lengkap tersimpan lokal di `storage/framework/testing/security-composer-audit.json` dan `storage/framework/testing/security-npm-audit.json`. Hasil registry adalah snapshot pada saat pemeriksaan, bukan jaminan bahwa tidak ada advisory baru setelahnya.
