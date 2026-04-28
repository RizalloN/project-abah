# 🔄 Dynamic IP Handling - Dokumentasi Lengkap

**Pertanyaan:** Jika router restart dan IP berubah, bagaimana sistem handle ini?

**Jawab Singkat:** Sistem otomatis handle IP change melalui **DuckDNS Dynamic DNS Client** yang berjalan di background PC Anda.

---

## 📊 Flow Diagram: IP Change Handling

```
┌─────────────────────────────────────────────────────────────┐
│                    ROUTER RESTART                           │
│              (IP: 110.136.24.119 → 110.136.25.xxx)          │
└────────────────────────┬────────────────────────────────────┘
                         │
                         ▼
        ┌─────────────────────────────────┐
        │  DuckDNS Client Running di PC   │
        │  (Background Service)            │
        └─────────────────────┬───────────┘
                              │
                    ┌─────────┴──────────┐
                    │                    │
         ┌──────────▼──────────┐  ┌──────▼───────────┐
         │ Detect New IP      │  │ Check Interval   │
         │ (setiap 5-10 min)  │  │ (configurable)   │
         └──────────┬──────────┘  └──────────────────┘
                    │
                    ▼
      ┌─────────────────────────────┐
      │ Send New IP to DuckDNS API  │
      └──────────┬──────────────────┘
                 │
                 ▼
      ┌─────────────────────────────┐
      │ DuckDNS Update DNS Record   │
      │ asixdashboard.duckdns.org   │
      │ → new IP address            │
      └──────────┬──────────────────┘
                 │
                 ▼
      ┌─────────────────────────────┐
      │ DNS Propagation (1-5 menit) │
      └──────────┬──────────────────┘
                 │
                 ▼
      ┌─────────────────────────────┐
      │ Project ACCESSIBLE dengan   │
      │ domain yang sama!           │
      │ asixdashboard.duckdns.org   │
      └─────────────────────────────┘
```

---

## 🛡️ Mechanism: Cara Sistem Handle IP Change

### **1. DuckDNS Dynamic DNS Client (Primary Solution)**

#### **Apa itu DuckDNS?**
- Free Dynamic DNS service
- Auto-update DNS records saat IP berubah
- Bekerja dengan DuckDNS client di PC Anda

#### **Bagaimana Kerjanya:**

```
TIMELINE:
├─ 00:00 - Router restart, IP berubah
│          IP: 110.136.24.119 → 110.136.25.200
│
├─ 00:00-00:05 - DuckDNS Client mendeteksi IP change
│                (polling setiap 5-10 menit)
│
├─ 00:05 - Client kirim new IP ke DuckDNS server
│          POST https://www.duckdns.org/update
│          Params: domain=asixdashboard, token=xxxxx, ip=110.136.25.200
│
├─ 00:05 - DuckDNS server update DNS record:
│          asixdashboard.duckdns.org → 110.136.25.200
│
├─ 00:05-00:10 - DNS propagation ke seluruh world
│
└─ 00:10 - ✓ Orang lain bisa akses dengan domain yang sama
            http://asixdashboard.duckdns.org
            (IP baru otomatis resolve ke 110.136.25.200)
```

#### **Setup DuckDNS Client:**

**Step 1: Download Client**
- Windows: https://www.duckdns.org/ (cari "install" → Windows)
- Atau gunakan: https://github.com/dt1/DuckDNS-Windows-Client

**Step 2: Install & Configure**
```
1. Download DuckDNS Windows Client
2. Extract file
3. Edit config dengan:
   - Domain: asixdashboard
   - Token: [get dari DuckDNS account]
4. Run duckdns.exe (akan minimize ke tray)
5. Setup auto-start (run on startup)
```

**Step 3: Verify Running**
```
- Taskbar akan ada DuckDNS icon
- Setiap 5 menit, client check & update IP
- Check log file untuk verify update
```

---

## 🔄 Timeline Saat Router Restart

### **Scenario: Router restart pukul 14:30**

```
WAKTU         | EVENT                              | STATUS
─────────────────────────────────────────────────────────────
14:30:00      | Router restart                     | ⚠️ DOWN
              | IP: 110.136.24.119 → ?           |
─────────────────────────────────────────────────────────────
14:30:30      | PC mendapat IP baru (DHCP)        | ⚠️ DOWN
              | IP baru: 110.136.25.200          |
─────────────────────────────────────────────────────────────
14:30:45      | Apache restart (jika auto)        | ⚠️ DOWN
              | Port 80 listening di IP baru      |
─────────────────────────────────────────────────────────────
14:35:00      | DuckDNS Client detect IP change   | 🔄 UPDATING
              | (polling interval tercapai)       |
─────────────────────────────────────────────────────────────
14:35:05      | DuckDNS send new IP ke server     | 🔄 UPDATING
─────────────────────────────────────────────────────────────
14:35:10      | DuckDNS API return success        | 🔄 UPDATING
              | DNS record updated                |
─────────────────────────────────────────────────────────────
14:35:30      | DNS propagation complete          | 🔄 UPDATING
              | Global DNS server sync'd          |
─────────────────────────────────────────────────────────────
14:36:00      | ✅ Project ONLINE again!          | ✅ ONLINE
              | asixdashboard.duckdns.org         |
              | → 110.136.25.200                  |
─────────────────────────────────────────────────────────────
```

**Total Downtime: ~6 menit** (bisa lebih cepat dengan client setting)

---

## ⚠️ Potential Issues & Solutions

### **Issue 1: DuckDNS Client Down (Crash/Hang)**

**Problem:**
- Client berhenti, IP tidak ter-update
- Domain masih pointing ke IP lama
- Akses down sampai client di-restart

**Detection:**
- User report "domain not accessible"
- DNS query masih return IP lama

**Solution:**
```bash
# Option A: Restart DuckDNS Client manually
- Stop DuckDNS client
- Restart DuckDNS client
- Wait 5 min untuk update

# Option B: Setup auto-restart (Task Scheduler)
- Create Windows Task
- Monitor DuckDNS process
- Auto-restart jika crash
- Check setiap 5 menit
```

**Best Practice:**
```powershell
# PowerShell script untuk auto-restart DuckDNS jika crash
# Save as: C:\Scripts\DuckDNS-Monitor.ps1

$duckdnsPath = "C:\path\to\duckdns.exe"
$processName = "duckdns"

while ($true) {
    $process = Get-Process $processName -ErrorAction SilentlyContinue
    
    if (-not $process) {
        Write-Host "DuckDNS not running, restarting..."
        Start-Process $duckdnsPath
        Start-Sleep -Seconds 5
    }
    
    Start-Sleep -Seconds 30  # Check setiap 30 detik
}
```

---

### **Issue 2: Router ISP Tidak Memberikan IP DHCP Otomatis**

**Problem:**
- Router hang/timeout saat restart
- Tidak dapat IP baru
- DuckDNS client dapat IP lama

**Prevention:**
```
Router Configuration:
├─ DHCP Server: Enable
├─ DHCP Lease Time: 24 jam (standard)
├─ Auto Reboot: Disable (jangan auto reboot)
└─ Static IP untuk PC: Consider menggunakan IP lokal statis
   (agar port forwarding tetap aman)
```

---

### **Issue 3: ISP Blocking Port 80**

**Problem:**
- Port 80 di-block ISP
- Akses gagal meski IP updated

**Solution:**
```
# Option 1: Gunakan port alternatif (8080, 3000, etc)
- Update Apache Listen port
- Update port forwarding di router
- Update APP_URL dengan :8080

# Option 2: Gunakan reverse proxy service
- Cloudflare (free)
- ngrok (tunnel service)
- AWS CloudFront

# Option 3: Hubungi ISP
- Request unblock port 80
- Atau upgrade internet plan
```

---

## 🔧 Recommended Setup untuk Reliability

### **1. DuckDNS Client with Auto-Start**

```batch
# Letakkan file ini di:
# C:\Users\msi\AppData\Roaming\Microsoft\Windows\Start Menu\Programs\Startup\DuckDNS.bat

@echo off
cd "D:\path\to\duckdns\"
start duckdns.exe
```

### **2. Apache Auto-Start on Boot**

```batch
# Create scheduled task untuk auto-start Apache
# Windows Task Scheduler:
# ├─ Trigger: At startup
# ├─ Action: Run D:\xampp\apache\bin\httpd.exe
# └─ Run with highest privileges
```

### **3. Monitoring Script (Optional)**

```powershell
# Script untuk monitor dan log IP changes
# Nama: Monitor-IP-Changes.ps1

$logFile = "D:\xampp\htdocs\project-ABAH\ip_change_log.txt"
$lastIP = ""

while ($true) {
    $currentIP = (Invoke-WebRequest -Uri "https://api.ipify.org" -UseBasicParsing).Content
    
    if ($currentIP -ne $lastIP) {
        $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
        "$timestamp | IP Changed: $lastIP → $currentIP" | Add-Content $logFile
        $lastIP = $currentIP
    }
    
    Start-Sleep -Seconds 60  # Check setiap menit
}
```

### **4. Health Check Script**

```powershell
# Monitor akses project setiap jam
$domain = "asixdashboard.duckdns.org"
$logFile = "D:\xampp\htdocs\project-ABAH\health_check_log.txt"

while ($true) {
    $timestamp = Get-Date -Format "yyyy-MM-dd HH:mm:ss"
    
    try {
        $response = Invoke-WebRequest -Uri "http://$domain" -TimeoutSec 5
        $status = "✓ OK (HTTP {0})" -f $response.StatusCode
    } catch {
        $status = "✗ FAIL ({0})" -f $_.Exception.Message
    }
    
    "$timestamp | Status: $status" | Add-Content $logFile
    
    Start-Sleep -Seconds 3600  # Check setiap jam
}
```

---

## 📋 Checklist: Setup Robust IP Handling

### **Essential (WAJIB):**
- [ ] Install DuckDNS Windows client
- [ ] Configure dengan domain & token
- [ ] Verify client running & updating
- [ ] Test IP change (manual di router)
- [ ] Verify domain masih accessible setelah change

### **Recommended (SANGAT DISARANKAN):**
- [ ] Setup auto-start DuckDNS client (Startup folder)
- [ ] Setup auto-start Apache service
- [ ] Create monitoring script
- [ ] Setup health check logging
- [ ] Regular test IP change simulation

### **Advanced (OPTIONAL):**
- [ ] Setup crash-recovery script untuk DuckDNS
- [ ] Setup auto-restart scheduler
- [ ] Setup email notification jika down
- [ ] Setup webhook untuk tracking IP changes
- [ ] Consider VPS/Cloud untuk production

---

## 🎯 Summary: Bagaimana IP Change Di-handle

| Komponen | Fungsi | Update Interval |
|----------|--------|-----------------|
| **Router** | Assign new IP via DHCP | ~30 detik setelah restart |
| **Apache** | Listen di IP baru | Auto (tetap di port 80) |
| **DuckDNS Client** | Detect & report IP change | 5-10 menit (configurable) |
| **DuckDNS Server** | Update DNS record | <1 menit |
| **Global DNS** | Propagate new record | 1-5 menit |
| **Users** | Access via domain | Auto resolve ke IP baru |

---

## ✅ Kesimpulan

**Sistem handle IP dinamis secara OTOMATIS melalui DuckDNS!**

Proses:
```
Router Restart → IP Berubah → DuckDNS Client Detect → Update DNS → 
Domain masih work dengan IP baru → 0 downtime (setelah initial setup)
```

**Waktu Downtime Saat IP Change:**
- Ideal: 5-10 menit (client detect + DNS propagate)
- Worst case: 15-30 menit (jika client crash)
- Mitigation: Setup monitoring & auto-restart

**Yang Paling Penting:**
1. ✅ DuckDNS client harus **ALWAYS RUNNING**
2. ✅ Apache harus **AUTO-START** saat boot
3. ✅ DuckDNS token harus **AMAN** (jangan share)
4. ✅ Periodically **TEST** IP change untuk verify setup

---

**Dokumentasi by:** Claude Code Assistant  
**Date:** 2026-04-28  
**Status:** Dynamic IP Handling Explained ✓

Pertanyaan: Apakah Anda ingin saya setup monitoring script untuk auto-detect IP changes?
