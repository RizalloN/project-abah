# 🚀 DuckDNS Automation Setup Guide

**Tujuan**: Membuat IP update otomatis setiap 5 menit tanpa perlu intervensi manual

**Status Sekarang**: 
- ✅ IP publik terdeteksi: 36.73.209.228
- ✅ Domain sudah terupdate: asixdashboard.duckdns.org
- ⏰ **Masalah**: Jika router restart lagi, IP berubah tapi domain tidak otomatis terupdate

**Solusi**: Setup Windows Task Scheduler untuk auto-update setiap 5 menit

---

## 📋 3-STEP SETUP GUIDE

### **STEP 1: Persiapan (2 menit)**

Pastikan Anda sudah memiliki:
- ✅ Token DuckDNS (sudah ada di script `UPDATE_DUCKDNS_IP.ps1`)
- ✅ Akses Administrator ke PowerShell
- ✅ Internet connection

---

### **STEP 2: Jalankan Setup Script (1 menit)**

**Buka PowerShell sebagai Administrator:**

```powershell
# Klik kanan desktop → Select "Windows PowerShell (Admin)"
# Atau cari "PowerShell" → right-click → "Run as Administrator"
```

**Jalankan setup script:**

```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\CREATE_DUCKDNS_SCHEDULER.ps1"
```

**Output yang diharapkan:**
```
========================================
  DuckDNS Task Scheduler Setup
========================================

[✓] Running as Administrator
[✓] Update script found
[✓] Token is configured
[✓] Log directory exists

Setting up Task Scheduler task...

[✓] Task registered successfully!
[✓] Verification: Task exists in Task Scheduler

Task Details:
  Name: DuckDNS-AutoUpdate
  Status: Enabled
  Trigger: Every 5 minutes
  Action: Update DuckDNS IP automatically

========================================
Setup Complete!
========================================

✅ DuckDNS automation is now ACTIVE!
```

---

### **STEP 3: Verifikasi Setup (1 menit)**

Jalankan verification script:

```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\VERIFY_DUCKDNS_SETUP.ps1"
```

**Output yang diharapkan:**
```
========================================
  DuckDNS Setup Verification
========================================

1. Script Files
✓ PASS - UPDATE script exists
✓ PASS - Token configured

2. Log Configuration
✓ PASS - Log directory exists

3. Task Scheduler
✓ PASS - Task registered
✓ PASS - Task enabled

4. Network Connectivity
✓ PASS - Public IP accessible: 36.73.209.228

5. DNS Resolution
✓ PASS - DNS resolves: asixdashboard.duckdns.org → 36.73.209.228
✓ PASS - DNS matches current IP

6. Recent Activity
✓ PASS - Log file exists

========================================
✅ VERIFICATION PASSED - Setup is complete!
========================================
```

**Jika ada FAIL:**
- Baca pesan error dengan teliti
- Perbaiki masalah yang disebutkan
- Jalankan verification script lagi

---

## 🎯 Apa yang Terjadi Setelah Setup

### **Skenario 1: Router Restart (Paling Penting)**

```
SEBELUM (tanpa automation):
T+0:00    Router restart → IP berubah
T+0:00    Domain BROKEN (masih ke IP lama)
T+5:00    Manual: Jalankan script UPDATE_DUCKDNS_IP.ps1
T+5:30    Domain kembali aktif
↓ 5+ MENIT DOWNTIME! ❌

SESUDAH (dengan automation):
T+0:00    Router restart → IP berubah
T+0:00    Domain masih berfungsi untuk <5 min
T+0:05    Auto-script deteksi IP change
T+0:05    Update DuckDNS server
T+0:10    Domain re-resolved to new IP
T+0:10    Layanan restored
↓ DOWNTIME ~5 MENIT (otomatis) ✅
```

### **Skenario 2: ISP Mega Update (Jarang)**

Jika ISP memberikan IP baru di tengah hari (VERY RARE):
- Script akan otomatis detect dalam 5 menit
- Domain terupdate otomatis
- Zero manual intervention

---

## 📊 Task Scheduler Details

### **Task Information**

| Property | Value |
|----------|-------|
| **Task Name** | DuckDNS-AutoUpdate |
| **Frequency** | Every 5 minutes |
| **Action** | Run `UPDATE_DUCKDNS_IP.ps1` |
| **Run Condition** | Network available |
| **Log Location** | `D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log` |
| **Status** | Enabled by default |

### **View Task in Windows**

1. **Open Task Scheduler:**
   - Press: `Ctrl + Shift + Esc` (or search "Task Scheduler")
   - Navigate to: `Task Scheduler Library`
   - Look for: `DuckDNS-AutoUpdate`

2. **Check Status:**
   - Right-click task → `Properties`
   - Tab "General" shows status
   - Tab "Triggers" shows "Every 5 minutes"
   - Tab "Actions" shows script path

3. **Manual Test (Optional):**
   - Right-click task → `Run`
   - Wait 10 seconds
   - Check log: `Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 10`

---

## 📝 Monitoring & Logs

### **View Log File**

```powershell
# View last 10 lines
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 10

# View last 50 lines (more context)
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 50

# Real-time monitoring (like "tail -f" on Linux)
# Requires PSReadLine module (built-in on Windows 10+)
# Install: Install-Module PSReadLine -Force (if needed)
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Wait -Tail 20
```

### **What Logs Look Like**

```
[2026-04-28 12:00:05] [START] === DuckDNS IP Update Started ===
[2026-04-28 12:00:06] [INFO] Current IP: 36.73.209.228
[2026-04-28 12:00:07] [INFO] DNS currently resolves to: 36.73.209.228
[2026-04-28 12:00:07] [SUCCESS] DNS is already up-to-date - no action needed

[2026-04-28 12:05:05] [START] === DuckDNS IP Update Started ===
[2026-04-28 12:05:06] [INFO] Current IP: 36.73.209.228
[2026-04-28 12:05:07] [INFO] DNS currently resolves to: 110.136.24.119
[2026-04-28 12:05:08] [SUCCESS] DuckDNS updated successfully: DuckDNS update successful
[2026-04-28 12:05:38] [SUCCESS] DNS verified updated to: 36.73.209.228
```

**Key Log Patterns:**
- `SUCCESS` = Everything OK
- `ERROR` = Something went wrong (check details)
- `WARNING` = Non-critical issue, will retry
- `INFO` = Status update

---

## 🛠️ Management Commands

### **Stop Automation (Temporarily)**

```powershell
# Disable task (stops auto-running, but keeps registration)
Disable-ScheduledTask -TaskName "DuckDNS-AutoUpdate"

# Later, re-enable:
Enable-ScheduledTask -TaskName "DuckDNS-AutoUpdate"
```

### **Remove Automation (Completely)**

```powershell
# Unregister task (completely removes it)
Unregister-ScheduledTask -TaskName "DuckDNS-AutoUpdate" -Confirm:$false

# Verify it's removed:
Get-ScheduledTask -TaskName "DuckDNS-AutoUpdate" -ErrorAction SilentlyContinue
# Should return: nothing (null)
```

### **Trigger Immediately (for Testing)**

```powershell
# Run task NOW (don't wait for 5-minute interval)
Start-ScheduledTask -TaskName "DuckDNS-AutoUpdate"

# Check result after 10 seconds
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 5
```

---

## 🚨 Troubleshooting

### **Problem: "Token invalid" errors in log**

**Cause**: Token changed on DuckDNS website  
**Fix**:
1. Login to https://www.duckdns.org/
2. Go to dashboard, find your domain
3. Copy fresh token from Docs tab
4. Edit `UPDATE_DUCKDNS_IP.ps1`
5. Replace token value
6. Save file
7. Task will use new token on next run

### **Problem: Task doesn't appear in Task Scheduler**

**Cause**: Task Scheduler doesn't show user tasks by default  
**Fix**:
```powershell
# Verify task exists in PowerShell:
Get-ScheduledTask -TaskName "DuckDNS-AutoUpdate"

# If it shows, but doesn't appear in UI:
# 1. Open Task Scheduler
# 2. View → Show hidden tasks
# 3. Refresh (F5)
```

### **Problem: Task exists but never runs**

**Cause**: Network not available or script has error  
**Fix**:
```powershell
# Check task status:
Get-ScheduledTask -TaskName "DuckDNS-AutoUpdate" | Select State, LastRunTime, LastTaskResult

# Run manually to see error:
Start-ScheduledTask -TaskName "DuckDNS-AutoUpdate"
Start-Sleep -Seconds 5
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 10

# If error is shown, run UPDATE_DUCKDNS_IP.ps1 directly to debug:
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
```

### **Problem: "Access Denied" when running setup**

**Cause**: Not running PowerShell as Administrator  
**Fix**:
1. Close PowerShell
2. Right-click Desktop
3. Select "Windows PowerShell (Admin)"
4. Re-run the CREATE_DUCKDNS_SCHEDULER.ps1 script

---

## ✅ Success Indicators

After setup is complete, you should see:

- ✅ Task appears in Task Scheduler: `DuckDNS-AutoUpdate`
- ✅ Task shows "Ready" status
- ✅ Log file gets updated every 5 minutes
- ✅ Log shows "SUCCESS" messages (or "already up-to-date" if IP hasn't changed)
- ✅ No "ERROR" messages in logs

---

## 📈 Long-Term Monitoring

### **Weekly Check**

Once a week, verify everything still works:

```powershell
# 1. Check log for errors
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 50 | grep -i "error|fail"

# 2. Verify DNS is up to date
nslookup asixdashboard.duckdns.org

# 3. Test web access
Invoke-WebRequest -Uri "http://asixdashboard.duckdns.org" -UseBasicParsing
```

### **If Something Breaks**

1. Run verification script: `VERIFY_DUCKDNS_SETUP.ps1`
2. Check logs for errors
3. Fix based on error message
4. Re-run CREATE_DUCKDNS_SCHEDULER.ps1
5. Verify with VERIFY_DUCKDNS_SETUP.ps1

---

## 🎓 Technical Details

### **Why Every 5 Minutes?**

| Interval | Pros | Cons |
|----------|------|------|
| **1 min** | Very responsive | High CPU/disk usage |
| **5 min** | Good balance ✓ | Small downtime window |
| **15 min** | Low overhead | More downtime (15 min) |
| **1 hour** | Very low overhead | Too much downtime |

**5 minutes is optimal** because:
- Router restart → IP change detected within 5 min
- Minimal system overhead (just 288 checks/day)
- Good UX (max 5-min downtime)

### **DNS Propagation**

Even after DuckDNS updates, DNS might take a few seconds to propagate:
1. Script updates DuckDNS: ~1 second
2. DuckDNS updates global DNS: ~5-10 seconds
3. ISP DNS cache refresh: ~5-15 seconds
4. Browser cache clear: ~0-30 seconds

**Total**: ~10-60 seconds for full propagation (usually ~20 seconds)

### **What Happens Each Run**

```
1. Script checks: What's my current public IP? (via api.ipify.org)
2. Script checks: What does asixdashboard.duckdns.org resolve to? (via DNS)
3. Compare results:
   - If SAME → "Already up-to-date" (log & exit)
   - If DIFFERENT → Call DuckDNS API with new IP
4. DuckDNS API responds: "OK" or "FAIL"
5. If OK → wait 30 sec for propagation → verify new IP
6. Log result + timestamp
```

---

## 📞 Need Help?

- **Task Scheduler issues**: Search "Windows Task Scheduler" or ask Windows Help
- **DuckDNS issues**: Go to https://www.duckdns.org/ → Support
- **PowerShell issues**: Run `Get-Help -Name <cmdlet-name>` in PowerShell

---

## 🎉 You're Done!

Once setup is complete:
- ✅ No more manual IP updates needed
- ✅ Domain stays accessible 24/7
- ✅ Automatic failover if IP changes
- ✅ Zero downtime after router restart

**Enjoy!** 🚀

---

**Files Referenced:**
- `CREATE_DUCKDNS_SCHEDULER.ps1` - Setup automation (run once)
- `VERIFY_DUCKDNS_SETUP.ps1` - Verification (run after setup)
- `UPDATE_DUCKDNS_IP.ps1` - Actual update script (auto-runs every 5 min)
- Log: `logs/duckdns.log` - Activity log
