# 🦆 DuckDNS Setup Guide - Lengkap untuk Anda

**Status Sekarang:**
- ✅ IP Publik: `36.73.209.228` (baru setelah router restart)
- ❌ DNS pointing ke: `110.136.24.119` (IP lama)
- ⚠️ DuckDNS Client: TIDAK BERJALAN

**Solusi:** Setup DuckDNS untuk auto-update domain ketika IP berubah

---

## 📋 **STEP 1: Create DuckDNS Account (Jika Belum Ada)**

1. **Buka website**: https://www.duckdns.org/
2. **Login dengan**:
   - Google
   - GitHub
   - Atau email (create account baru)
3. **Verify email** jika diminta

---

## 🔑 **STEP 2: Create Domain & Get Token**

Setelah login:

1. **Di Dashboard**, lihat field "Sub Domain"
2. **Ketik**: `asixdashboard` (atau nama favorit Anda)
3. **Click "add domain"**
4. **Token akan muncul di tab "Docs"**

```
Example Token: abc12345-def6-7890-ghij-klmnopqrstuv
```

**⚠️ PENTING:**
- Simpan token dengan aman (jangan share ke orang lain)
- Ini adalah "password" untuk domain Anda

---

## 🚀 **STEP 3: Update Script dengan Token Anda**

1. **Buka file**: `D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1`
2. **Find line 7:**
   ```powershell
   $DUCKDNS_TOKEN = "YOUR_TOKEN_HERE"
   ```
3. **Replace dengan token Anda:**
   ```powershell
   $DUCKDNS_TOKEN = "abc12345-def6-7890-ghij-klmnopqrstuv"
   ```
4. **Save file** (Ctrl+S)

---

## ⚡ **STEP 4: Update IP SEKARANG (Manual)**

Jalankan script untuk langsung update IP ke DuckDNS:

```powershell
# Buka PowerShell sebagai Administrator
# Ketik command berikut:

powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
```

**Atau cukup double-click file:**
```
D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1
```

**Output yang diharapkan:**
```
✓ Current Public IP: 36.73.209.228
✓ DNS currently resolves to: 110.136.24.119
⚠ IP MISMATCH DETECTED!

Updating DuckDNS with new IP...
✓ DuckDNS Updated: DuckDNS update successful

Waiting for DNS propagation (30 seconds)...

✅ SUCCESS! DNS is now updated:
   asixdashboard.duckdns.org → 36.73.209.228
```

---

## 🔄 **STEP 5: Setup Auto-Update (Jika Ingin Permanent Solution)**

Anda punya 2 opsi:

### **Option A: Setup Automatic Monitoring (RECOMMENDED)**

**File sudah tersedia**: `Monitor-IP-Changes.ps1`

**Cara Setup:**
1. Edit file, configure domain dan token
2. Setup Windows Task Scheduler untuk run saat startup
3. Script akan auto-detect IP changes dan update DuckDNS

### **Option B: Install DuckDNS Windows Client**

**Download dari:**
- https://www.duckdns.org/ (cari Windows tab)
- Atau: https://github.com/dt1/DuckDNS-Windows-Client

**Setup:**
1. Download executable
2. Extract dan run
3. Configure dengan domain & token
4. Minimize ke system tray
5. Auto-update setiap 5-10 menit

---

## ✅ **VERIFICATION: Test DNS Update**

### **Test 1: Verify DNS Resolve**

**Via Command Line:**
```powershell
nslookup asixdashboard.duckdns.org
```

**Expected Output:**
```
Name:    asixdashboard.duckdns.org
Address: 36.73.209.228   ✓ (should match your current IP)
```

### **Test 2: Verify Web Access**

**Di Browser, buka:**
```
http://asixdashboard.duckdns.org
```

**Expected:** Halaman project Anda muncul (jika Apache running)

### **Test 3: Force IP Change & Monitor**

**To simulate router restart:**
1. Disconnect WiFi / ethernet
2. Wait untuk new IP dari ISP
3. Reconnect
4. Run `UPDATE_DUCKDNS_IP.ps1` script
5. Verify domain still accessible

---

## 📊 **Complete Flow After Setup**

```
Skenario: Router restart kapan saja

T+0:00
├─ Router restart
├─ New DHCP IP assigned (e.g., 36.73.209.xxx)
└─ Status: 🔴 Domain pointing ke IP lama

T+0:05-0:10 (Manual Script)
├─ Run UPDATE_DUCKDNS_IP.ps1
├─ Script detects IP change
├─ Updates DuckDNS server
└─ Status: 🟡 Updating...

T+0:10-0:15
├─ DNS propagation
├─ Global DNS servers sync
└─ Status: 🟢 Domain accessible dengan IP baru

Result:
asixdashboard.duckdns.org → 36.73.209.xxx (auto-updated!)
```

---

## 🆘 **Troubleshooting**

### **Problem 1: Script says "Invalid Token"**

**Cause**: Token salah atau expired  
**Fix**:
1. Login ke https://www.duckdns.org/ lagi
2. Verify domain masih ada di account
3. Copy token lagi (bisa ada perubahan)
4. Update script dengan token baru

### **Problem 2: DNS masih pointing ke IP lama**

**Cause**: DuckDNS update gagal atau propagation belum complete  
**Fix**:
```powershell
# Tunggu 5-10 menit, coba lagi:
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"

# Atau check via:
nslookup asixdashboard.duckdns.org
```

### **Problem 3: Domain tetap down meski DNS updated**

**Cause**: Apache tidak running  
**Fix**:
```
1. Start Apache: D:\XAMPP\apache\bin\httpd.exe
2. Atau set Apache untuk auto-start saat boot
```

---

## 📋 **Setup Checklist**

- [ ] Create DuckDNS account (https://www.duckdns.org/)
- [ ] Create domain: `asixdashboard`
- [ ] Copy token dari DuckDNS dashboard
- [ ] Edit `UPDATE_DUCKDNS_IP.ps1` dengan token
- [ ] Run script untuk update IP sekarang
- [ ] Test DNS resolution: `nslookup asixdashboard.duckdns.org`
- [ ] Test web access: `http://asixdashboard.duckdns.org`
- [ ] (Optional) Setup Windows Task Scheduler untuk auto-run monitoring script

---

## 🎯 **Expected Results**

**After Setup:**
- ✅ Domain accessible via: `asixdashboard.duckdns.org`
- ✅ Auto-resolves ke current IP
- ✅ Survives router restart
- ✅ No need to share/change IP to users
- ✅ IP changes handled transparently

---

## 📞 **Quick Commands Reference**

```powershell
# Get current IP
curl https://api.ipify.org

# Check DNS resolution
nslookup asixdashboard.duckdns.org

# Manual DuckDNS update
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"

# Check if Apache running
tasklist | findstr apache

# Check if DuckDNS client running
tasklist | findstr duckdns
```

---

## 🎓 **How It Works (Technical Explanation)**

**Normal DNS:**
```
User → Google DNS Servers → "asixdashboard.com" → ?????
(Static - you need to own domain)
```

**DuckDNS (Dynamic):**
```
User → Google DNS Servers → "asixdashboard.duckdns.org" → Your IP
(Auto-updated by DuckDNS client or script when IP changes)
```

**When Router Restarts:**
```
Router: 110.136.24.119 → 36.73.209.228 (new IP from ISP)
        ↓
Your PC: DHCP client gets new IP
        ↓
DuckDNS Client/Script: "Hey DuckDNS, my IP is now 36.73.209.228"
        ↓
DuckDNS: Updates DNS record globally
        ↓
Users: Still access asixdashboard.duckdns.org (transparently updated!)
```

---

**Setup time: ~5 minutes**  
**Benefit: Zero downtime IP changes!**

Pertanyaan? Lihat `DYNAMIC_IP_HANDLING.md` untuk detail lebih lanjut.
