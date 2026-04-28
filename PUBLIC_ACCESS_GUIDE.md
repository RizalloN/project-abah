# Setup Public Access untuk Project ABAH

**Status:** ✓ Server Apache running  
**Current IP:** 110.136.24.119  
**App URL:** http://110.136.24.119  
**Timezone:** Asia/Jakarta  

---

## 📋 Konfigurasi yang Sudah Dilakukan

✓ **Virtual Host Apache** - Dikonfigurasi di `D:/xampp/apache/conf/extra/httpd-vhosts.conf`
- DocumentRoot: `D:/xampp/htdocs/project-ABAH/public`
- ServerName: `110.136.24.119`
- Rewrite rules untuk Laravel sudah aktif

✓ **.env Configuration** - Update dengan APP_URL publik
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=http://110.136.24.119`

✓ **Apache Service** - Sudah running (httpd.exe processes aktif)

---

## 🌐 Langkah-Langkah Selanjutnya untuk Public Access

### **LANGKAH 1: Setup Port Forwarding di Router**

Karena project Anda running di PC lokal, Anda perlu forward port 80 dari router ke PC Anda:

1. **Buka Router Admin Panel**
   - Buka browser: `http://192.168.1.1` (atau IP router Anda)
   - Login dengan username/password router (biasanya admin/admin)
   - Cari menu: **Port Forwarding** atau **NAT**

2. **Konfigurasi Port Forward**
   - External Port: `80` (HTTP)
   - Internal IP: `192.168.x.x` (IP lokal PC Anda - cari dengan `ipconfig`)
   - Internal Port: `80`
   - Protocol: `TCP`
   - Enable: ✓ Yes

3. **Verifikasi IP Lokal Anda**
   ```bash
   ipconfig
   ```
   Cari IPv4 Address di bagian "Ethernet adapter" atau "Wireless LAN"
   Contoh: `192.168.1.100`

### **LANGKAH 2: Setup Dynamic DNS (PENTING!)**

Karena IP Anda dinamis (berubah-ubah), Anda perlu setup Dynamic DNS agar akses tetap stabil:

**Opsi A: Menggunakan Free Dynamic DNS Services**

1. **Daftar di Salah Satu Service Berikut:**
   - **NoIP.com** (Gratis 30 hari)
   - **DuckDNS.org** (Gratis)
   - **Dynv6.com** (Gratis)
   - **ChangeIP.com** (Gratis)

2. **Setelah Daftar:**
   - Buat hostname: contoh `project-abah.duckdns.org`
   - Download Dynamic DNS client untuk Windows
   - Install dan run client (akan auto-update IP Anda)

3. **Update .env dengan Domain:**
   ```bash
   APP_URL=http://project-abah.duckdns.org
   ```

4. **Update Virtual Host Apache:**
   ```apache
   ServerName project-abah.duckdns.org
   ServerAlias 110.136.24.119
   ```

**Opsi B: Update Manual IP ke .env**
Jika IP berubah, update `APP_URL` di `.env`:
```bash
APP_URL=http://110.136.24.119
```

### **LANGKAH 3: Test Public Access**

Setelah port forwarding dan DNS setup:

1. **Test dari PC lain atau Mobile:**
   ```
   http://110.136.24.119
   http://project-abah.duckdns.org (jika sudah setup DNS)
   ```

2. **Test dari Online Tools:**
   - https://www.isitup.org/
   - https://downforeveryoneorjustme.com/

3. **Check Port:**
   - https://www.canyouseeme.org/
   - Input: Port 80, IP 110.136.24.119

### **LANGKAH 4: Security Checklist**

⚠️ **Sebelum public, pastikan:**

- [ ] Set `APP_DEBUG=false` di .env ✓
- [ ] Set `APP_ENV=production` di .env ✓
- [ ] Setup environment yang aman untuk database
- [ ] Jangan expose `.env` file
- [ ] Setup HTTPS (SSL Certificate) - *strongly recommended*
- [ ] Configure firewall untuk hanya allow port 80 dan 443
- [ ] Regular backup database
- [ ] Setup monitoring dan logging
- [ ] Update semua dependencies Laravel

### **LANGKAH 5: Setup HTTPS (SSL/TLS) - Recommended**

Untuk mengamankan koneksi:

1. **Gunakan Let's Encrypt (Gratis)**
   - Install Certbot untuk Windows
   - Setup auto-renewal
   - Update Apache configuration

2. **Update .env:**
   ```bash
   APP_URL=https://110.136.24.119
   ```

3. **Enable mod_ssl di Apache**

---

## 🔧 Maintenance & Troubleshooting

### **Restart Apache (jika ada error)**
```bash
# Pastikan running sebagai Administrator
D:\xampp\apache\bin\httpd.exe -k restart -n Apache2.4

# Atau gunakan XAMPP Control Panel
```

### **Check Apache Error Log**
```bash
# Jika ada error, lihat log di:
D:\xampp\apache\logs\project-abah-error.log
D:\xampp\apache\logs\project-abah-access.log
```

### **Database Connection dari Luar**
Update `.env` jika ada masalah database:
```bash
# Jangan expose 3306, hanya allow lokal atau setup proxy
DB_HOST=127.0.0.1
```

### **Clear Laravel Cache Setelah Deploy**
```bash
cd D:\xampp\htdocs\project-ABAH
php artisan config:cache
php artisan view:cache
php artisan route:cache
```

---

## 📊 Current Configuration Summary

| Item | Value |
|------|-------|
| **Server** | Apache 2.4 (XAMPP) |
| **Framework** | Laravel |
| **App Name** | Dashboard A-Six |
| **Public IP** | 110.136.24.119 |
| **Port** | 80 (HTTP) / 443 (HTTPS) |
| **Document Root** | `D:/xampp/htdocs/project-ABAH/public` |
| **Database** | MySQL (Local) |
| **Environment** | Production |
| **Debug** | Disabled |
| **Timezone** | Asia/Jakarta |

---

## 🚀 Quick Status Check

```bash
# Check Apache Status
tasklist | grep httpd.exe

# Check Virtual Host Config
D:\xampp\apache\bin\httpd.exe -S

# Check Port Listening
netstat -ano | findstr :80

# Test Local Access
curl http://localhost/

# Test with IP
curl http://110.136.24.119/
```

---

## ⚠️ Important Notes

1. **Dynamic IP Warning**: IP 110.136.24.119 bisa berubah kapan saja. Gunakan Dynamic DNS untuk solusi stabil.

2. **ISP Port Blocking**: Beberapa ISP block port 80. Jika tidak bisa akses, coba gunakan port alternatif (8080) atau hubungi ISP.

3. **Firewall**: Pastikan Windows Firewall allow port 80 untuk httpd.exe

4. **Power**: Jangan matikan PC agar project tetap online 24/7. Pertimbangkan VPS/Cloud Hosting untuk production.

5. **Backup**: Setup regular backup untuk database dan file project.

---

**Setup by:** Claude Code Assistant  
**Date:** 2026-04-28  
**Status:** Ready for Public Access ✓
