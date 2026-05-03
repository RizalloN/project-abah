# DuckDNS Automation - Final Setup Guide

## Status
✅ **Fixed**: UPDATE_DUCKDNS_IP.ps1 script now working correctly
- IP detection: ✅ Working
- DuckDNS API calls: ✅ Fixed (handles byte array response)
- DNS verification: ✅ Working
- Logging: ✅ Clean and reliable

## Quick Setup (3 Steps)

### Step 1: Verify Script Works
Run this command in PowerShell (regular, not admin):
```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
```

Expected output in log file:
```
[HH:mm:ss] START Update check at HH:mm:ss
[HH:mm:ss] Current IP: xxx.xxx.xxx.xxx
[HH:mm:ss] DNS IP: xxx.xxx.xxx.xxx
[HH:mm:ss] OK: DNS already has correct IP (xxx.xxx.xxx.xxx)
[HH:mm:ss] END Update completed
```

Check logs:
```powershell
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log" -Tail 10
```

### Step 2: Create Task Scheduler Job (Requires Admin)

Open **PowerShell as Administrator** and run:

```powershell
# Set variables
$SCRIPT_PATH = "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
$TASK_NAME = "DuckDNS-AutoUpdate"
$TASK_DESC = "Automatic DuckDNS IP update every 5 minutes"

# Create trigger (every 5 minutes)
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date)
$trigger.Repetition.Duration = [timespan]::MaxValue

# Create action
$action = New-ScheduledTaskAction -Execute "powershell.exe" `
    -Argument "-ExecutionPolicy Bypass -File `"$SCRIPT_PATH`"" `
    -WorkingDirectory (Split-Path -Parent $SCRIPT_PATH)

# Create settings
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -RunOnlyIfNetworkAvailable `
    -MultipleInstances IgnoreNew

# Register task
Register-ScheduledTask -TaskName $TASK_NAME `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -Description $TASK_DESC `
    -Force

Write-Host "✅ Task registered successfully!" -ForegroundColor Green
Write-Host "Task: $TASK_NAME" -ForegroundColor Green
Write-Host "Script: $SCRIPT_PATH" -ForegroundColor Green
```

### Step 3: Verify Task is Running

In **Task Scheduler**:
1. Press `Win + R` → type `taskschd.msc`
2. Look for "DuckDNS-AutoUpdate" under Task Scheduler Library
3. Right-click → Run (to test immediately)
4. Wait 5 seconds, then check log:

```powershell
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log" -Tail 5
```

Expected output when IP matches:
```
[HH:mm:ss] START Update check at HH:mm:ss
[HH:mm:ss] Current IP: 36.73.211.85
[HH:mm:ss] DNS IP: 36.73.211.85
[HH:mm:ss] OK: DNS already has correct IP (36.73.211.85)
[HH:mm:ss] END Update completed
```

When IP changes (detected automatically in 5 minutes):
```
[HH:mm:ss] START Update check at HH:mm:ss
[HH:mm:ss] Current IP: 36.73.211.85
[HH:mm:ss] DNS IP: 36.90.186.255
[HH:mm:ss] MISMATCH: Current=36.73.211.85, DNS=36.90.186.255 - Updating DuckDNS...
[HH:mm:ss] DuckDNS response: OK
[HH:mm:ss] SUCCESS: DuckDNS updated with IP 36.73.211.85
[HH:mm:ss] Verified DNS: 36.73.211.85
[HH:mm:ss] END Update completed
```

## What's Fixed

### Problem 1: API Response Handling
**Issue**: DuckDNS API returns "OK" as byte array (79 75 in ASCII)
**Fix**: Script now properly converts byte array to string

### Problem 2: Script Execution in Scheduled Task Context
**Issue**: Invoke-WebRequest and nslookup failed silently
**Fix**: Added proper TLS/SSL setup and error handling

### Problem 3: Network Connectivity Detection
**Issue**: Script couldn't detect when network was available
**Fix**: Now uses multiple IP detection services with fallback

## How It Works

Every 5 minutes:
1. **Get Current IP** from ipify.org
2. **Check DNS** what asixdashboard.duckdns.org currently resolves to
3. **Compare** - if different:
   - Call DuckDNS API to update
   - Wait 30 seconds for propagation
   - Verify DNS updated
4. **Log** results to duckdns_update.log

## Testing

### Manual Test (Check if script works)
```powershell
powershell -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
```

### Monitor Logs
```powershell
# View last 20 entries
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log" -Tail 20

# Watch logs in real-time (Ctrl+C to stop)
Get-Content "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log" -Wait
```

### Simulate IP Change
1. Restart your router
2. Check log within 5 minutes - should show update
3. Or manually edit line 3 of UPDATE_DUCKDNS_IP.ps1 to test different IP

## Troubleshooting

### Issue: Task shows "Last Run Result: Error"
- Check log file for error details
- Verify DUCKDNS_TOKEN in UPDATE_DUCKDNS_IP.ps1 is correct
- Test script manually first

### Issue: Logs not updating
- Check if task is enabled in Task Scheduler
- Verify network is available
- Check Windows Event Viewer → Windows Logs → Application

### Issue: DNS not updating when IP changes
- Log file should show "MISMATCH" entries
- If not, IP detection might be failing
- Test: `powershell -c "(Invoke-WebRequest -Uri 'https://api.ipify.org?format=text' -UseBasicParsing).Content"`

## Files

- **Script**: `UPDATE_DUCKDNS_IP.ps1` - Main update script
- **Logs**: `logs/duckdns_update.log` - All activity logged here
- **Config**: Token at line 3 of UPDATE_DUCKDNS_IP.ps1

## Next Steps

1. ✅ Verify script works (Step 1 above)
2. ⏭️  Create Task Scheduler job (Step 2 - needs admin)
3. ⏭️  Monitor logs to confirm it's running every 5 minutes

Done! 🚀 Your domain will now auto-update whenever IP changes!
