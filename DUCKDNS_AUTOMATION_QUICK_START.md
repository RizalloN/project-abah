# ⚡ DuckDNS Automation - Quick Start (3 Minutes)

## 🎯 Do This NOW

### **Step 1: Open PowerShell as Administrator**

```
1. Right-click Desktop
2. Select "Windows PowerShell (Admin)"
   (or search "PowerShell" → right-click → "Run as Administrator")
```

### **Step 2: Run Setup Script**

```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\CREATE_DUCKDNS_SCHEDULER.ps1"
```

Wait for output showing: **✅ DuckDNS automation is now ACTIVE!**

### **Step 3: Verify It Works**

```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\VERIFY_DUCKDNS_SETUP.ps1"
```

Wait for output showing: **✅ VERIFICATION PASSED**

## ✅ Done!

Your domain will now auto-update every 5 minutes if IP changes.

---

## 📊 What Happens Next

| When | What | Result |
|------|------|--------|
| **Every 5 min** | Auto-script runs | Checks if IP changed |
| **Router restarts** | IP changes | Script detects within 5 min |
| **After detection** | DuckDNS updated | Domain points to new IP |
| **After 30 sec** | DNS propagates | Domain accessible again |

---

## 🔍 Monitor (Optional)

```powershell
# View latest activity
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log" -Tail 10
```

---

## 🛑 Stop Automation (If Needed)

```powershell
# Disable:
Disable-ScheduledTask -TaskName "DuckDNS-AutoUpdate"

# Re-enable:
Enable-ScheduledTask -TaskName "DuckDNS-AutoUpdate"

# Remove completely:
Unregister-ScheduledTask -TaskName "DuckDNS-AutoUpdate" -Confirm:$false
```

---

**That's it! You're done.** 🎉
