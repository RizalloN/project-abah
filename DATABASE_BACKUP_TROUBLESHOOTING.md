# Database Backup Troubleshooting Guide

## Error: "Can't create TCP/IP socket (10106)"

Kesalahan ini muncul ketika `mysqldump` tidak bisa terhubung ke MySQL server.

### Penyebab Umum

1. **MySQL Server tidak running** - Penyebab paling sering
2. **Path mysqldump.exe salah atau tidak ditemukan**
3. **Konfigurasi database di .env tidak sesuai**
4. **Port MySQL berbeda dari konfigurasi**

### Solusi

#### 1. Pastikan MySQL Server Berjalan

**XAMPP Control Panel:**
- Buka XAMPP Control Panel
- Pastikan **MySQL** sudah ditekan tombol "Start" (status hijau)
- Jika belum berjalan, klik "Start" untuk menjalankan MySQL

**Via Command Prompt:**
```bash
# Check apakah MySQL port 3306 aktif
netstat -an | findstr 3306
```

#### 2. Verifikasi Konfigurasi Database (.env)

File: `c:\xampp\htdocs\project-ABAH\.env`

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_abah
DB_USERNAME=root
DB_PASSWORD=
DB_MYSQL_LOCAL_INFILE=true
```

**Catatan untuk XAMPP:**
- `DB_HOST` harus `127.0.0.1` atau `localhost`
- `DB_PORT` default XAMPP adalah `3306`
- `DB_USERNAME` default XAMPP adalah `root`
- `DB_PASSWORD` biasanya kosong untuk XAMPP local

#### 3. Cek Path mysqldump.exe

**Path standar XAMPP Windows:**
```
C:\xampp\mysql\bin\mysqldump.exe
```

**Alternatif:**
- `C:\Program Files\MySQL\MySQL Server 8.0\bin\mysqldump.exe`
- `C:\Program Files\MariaDB 11.0\bin\mysqldump.exe`

**Jika perlu custom path:**
Tambahkan ke file `.env`:
```ini
MYSQLDUMP_BINARY=C:\xampp\mysql\bin\mysqldump.exe
```

#### 4. Test Koneksi MySQL Secara Manual

**Via Command Prompt:**
```bash
# Navigate ke MySQL bin directory
cd C:\xampp\mysql\bin

# Test koneksi
mysql -h 127.0.0.1 -u root -p

# Jika password kosong, tekan Enter saat diminta password
# Jika berhasil, akan masuk ke MySQL prompt (mysql>)
exit
```

#### 5. Buat Backup Via Terminal

Jika UI masih error, coba via terminal:

```bash
# Navigate ke project
cd c:\xampp\htdocs\project-ABAH

# Run backup command
php artisan db:backup-progressive test_backup_123
```

**Atau gunakan mysqldump langsung:**
```bash
cd C:\xampp\mysql\bin

mysqldump -h 127.0.0.1 -u root project_abah > backup.sql
```

#### 6. Restart MySQL Service

**XAMPP Control Panel:**
- Klik "Stop" untuk MySQL
- Tunggu beberapa detik
- Klik "Start" untuk MySQL lagi

**Via Command Prompt (Administrator):**
```bash
net stop MySQL80
net start MySQL80
```

(Ganti `MySQL80` dengan versi MySQL Anda yang terinstall)

## Improvements Made

File backup sudah diperbaiki dengan:

1. ✅ **Improved environment inheritance** - Sistem environment variables sekarang properly dipass ke mysqldump
2. ✅ **Windows-specific protocol handling** - Explicit TCP protocol untuk Windows
3. ✅ **Better error messages** - Pesan error lebih detail dengan saran solusi
4. ✅ **Connection diagnostics** - Error detection untuk socket/connection issues

## Files Modified

- `app/Services/DatabaseBackupService.php` - Connection handling improvements
- `app/Console/Commands/ProgressiveBackupCommand.php` - Environment inheritance fix

## Testing Backup

1. Buka **File Management** di dashboard
2. Klik **"Backup Database"** button
3. Tunggu proses selesai (perhatikan progress bar)
4. Backup file akan disimpan di: `storage/app/private/database_backups/`

## Verified MySQL Connection

Untuk memastikan koneksi MySQL berfungsi:

```bash
# Via Laravel Tinker
php artisan tinker

# Check database connection
DB::connection()->getPdo()

# If successful, exit
exit
```

## Contact Support

Jika masih mengalami masalah:
- Check XAMPP MySQL error log: `C:\xampp\mysql\data\*.err`
- Pastikan port 3306 tidak digunakan aplikasi lain
- Coba restart komputer Anda
