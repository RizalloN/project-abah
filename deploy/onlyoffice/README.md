# ONLYOFFICE untuk DriveASIX

DriveASIX memakai ONLYOFFICE Document Server sebagai mesin editor full-fidelity
untuk DOCX, PPTX, dan XLSX. Document Server berjalan pada host Linux/container
terpisah; file asli tetap berada di storage private Laravel pada server XAMPP.

## Arsitektur

```text
Browser
  |-- https://asixdashboard.online --------> Laravel/XAMPP
  `-- https://office.asixdashboard.online -> Cloudflare Tunnel
                                              `-> 127.0.0.1:8080 (Document Server)

Document Server
  `-- source + callback bertoken ----------> https://asixdashboard.online
```

Host aplikasi dan Document Server tidak harus satu mesin. Document Server harus
dapat menjangkau URL source dan callback DriveASIX, sedangkan browser harus
dapat menjangkau `ONLYOFFICE_PUBLIC_URL`.

## Prasyarat host dokumen

- Linux 64-bit dengan Docker Engine dan Docker Compose v2.
- Resource CPU/RAM/disk memadai untuk jumlah editor bersamaan.
- Cloudflare Tunnel berjalan pada host yang sama, atau meneruskan hostname
  `office.asixdashboard.online` ke host tersebut.
- DNS/HTTPS publik aktif dan WebSocket tidak diblokir.
- Image ONLYOFFICE dipilih dari rilis yang masih didukung, ditinjau, lalu
  dipin ke tag spesifik atau digest. Compose sengaja tidak memakai `latest`.

XAMPP Windows yang menjalankan Laravel tidak perlu menjalankan container ini.

## Menjalankan Document Server

1. Salin folder `deploy/onlyoffice` ke host Linux.
2. Buat file `.env` di samping `docker-compose.yml` dan jangan commit:

   ```dotenv
   ONLYOFFICE_IMAGE=onlyoffice/documentserver:<tag-yang-sudah-ditinjau>
   ONLYOFFICE_BIND_PORT=8080
   ONLYOFFICE_JWT_SECRET=<secret-random-panjang>
   ONLYOFFICE_JWT_HEADER=AuthorizationJwt
   ```

3. Batasi permission secret lalu jalankan:

   ```bash
   chmod 600 .env
   docker compose pull
   docker compose up -d
   docker compose ps
   curl --fail http://127.0.0.1:8080/healthcheck
   ```

4. Pastikan volume bernama `onlyoffice_data`, `onlyoffice_logs`,
   `onlyoffice_lib`, dan `onlyoffice_postgresql` masuk kebijakan backup.

Jangan membuka port 8080 ke internet. Compose mengikatnya ke loopback agar hanya
Cloudflare Tunnel pada host tersebut yang dapat mengaksesnya.

## Cloudflare Tunnel

Tambahkan public hostname:

```text
office.asixdashboard.online -> http://127.0.0.1:8080
```

- WebSocket harus diteruskan.
- Jangan pasang halaman login Cloudflare Access/interstitial di hostname
  editor karena `api.js`, frame editor, dan koneksi WebSocket harus dapat
  dimuat langsung oleh browser.
- Proteksi dokumen dilakukan oleh JWT dan URL akses sementara dari Laravel.
- Jangan cache respons editor maupun endpoint source/callback DriveASIX.
- Hindari Bot Fight/WAF challenge pada endpoint source dan callback karena
  pemanggilnya adalah Document Server, bukan browser interaktif.

## Konfigurasi Laravel

Gunakan secret dan nama header yang sama persis dengan container:

```dotenv
ONLYOFFICE_ENABLED=true
ONLYOFFICE_PUBLIC_URL=https://office.asixdashboard.online
ONLYOFFICE_INTERNAL_URL=https://office.asixdashboard.online
ONLYOFFICE_APP_URL=https://asixdashboard.online
ONLYOFFICE_JWT_SECRET=<secret-yang-sama-dengan-container>
ONLYOFFICE_JWT_HEADER=AuthorizationJwt
ONLYOFFICE_ALLOWED_DOWNLOAD_ORIGINS=https://office.asixdashboard.online
ONLYOFFICE_ACCESS_TTL_MINUTES=1440
ONLYOFFICE_TIMEOUT_SECONDS=120
ONLYOFFICE_MAX_DOWNLOAD_BYTES=52428800
ONLYOFFICE_VERIFY_TLS=true
```

Setelah mengubah environment:

```bash
php artisan config:clear
php artisan config:cache
```

`ONLYOFFICE_INTERNAL_URL` adalah alamat yang dipakai Laravel saat mengambil
hasil save. Bila tersedia jalur LAN privat yang tervalidasi, alamat itu dapat
dipakai dan origin-nya harus ditambahkan ke
`ONLYOFFICE_ALLOWED_DOWNLOAD_ORIGINS`. Jangan menonaktifkan verifikasi TLS untuk
URL publik.

## Validasi sebelum produksi

1. Buka DOCX, PPTX, dan XLSX dari akun user biasa.
2. Simpan perubahan, tutup seluruh editor, lalu unduh ulang file.
3. Pastikan ukuran, MIME, dan revision berubah serta backup versi terbentuk.
4. Uji dua browser pada file yang sama untuk kolaborasi dan konflik revisi.
5. Putuskan Document Server saat edit dan pastikan file asli tidak tertimpa.
6. Uji JWT salah/kedaluwarsa serta URL hasil save dari origin yang tidak ada
   pada allowlist.
7. Periksa font dokumen. Fidelity layout bergantung pada font yang tersedia di
   Document Server; pasang font perusahaan melalui prosedur resmi image yang
   dipin, kemudian regenerasi cache font.
8. Pantau disk volume dan rotasi log.

## Operasional dan lisensi

- Backup volume dan file DriveASIX sebelum upgrade image.
- Uji round-trip dokumen representatif di staging sebelum setiap upgrade.
- Ganti JWT secret secara terkoordinasi antara Laravel dan Document Server.
- Tinjau ketentuan lisensi edisi ONLYOFFICE yang digunakan, kewajiban open
  source, batas penggunaan, dukungan, dan kebutuhan lisensi komersial bersama
  pihak legal/IT sebelum produksi.
- “Full-fidelity” tetap berarti kompatibilitas terbaik dari mesin ONLYOFFICE;
  hasil dapat berbeda bila font, macro, add-in, atau fitur proprietary Microsoft
  Office tidak tersedia.
