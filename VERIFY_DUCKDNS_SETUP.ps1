# DuckDNS Setup Verification Script
# Script ini mengecek semua komponen DuckDNS setup
# Run: powershell -ExecutionPolicy Bypass -File VERIFY_DUCKDNS_SETUP.ps1

# CONFIGURATION
$TASK_NAME = "DuckDNS-AutoUpdate"
$SCRIPT_PATH = "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
$LOG_PATH = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log"
$DOMAIN = "asixdashboard"

# HELPER FUNCTIONS

function Write-CheckResult {
    param([string]$name, [bool]$passed, [string]$details = "")
    $status = if ($passed) { "OK" } else { "FAIL" }
    $color = if ($passed) { "Green" } else { "Red" }
    Write-Host $status -ForegroundColor $color -NoNewline
    Write-Host " - $name"
    if ($details) { Write-Host "    $details" -ForegroundColor Gray }
}

function Get-PublicIP {
    try {
        $ip = (Invoke-WebRequest -Uri "https://api.ipify.org" -UseBasicParsing -TimeoutSec 5).Content.Trim()
        return $ip
    }
    catch { return $null }
}

function Get-DNSResolvedIP {
    try {
        $ip = [System.Net.Dns]::GetHostAddresses("$DOMAIN.duckdns.org") | Select-Object -First 1 -ExpandProperty IPAddressToString
        return $ip
    }
    catch { return $null }
}

# MAIN VERIFICATION

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "  DuckDNS Setup Verification" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

$allPassed = $true

# CHECK 1: Script files
Write-Host "1. Script Files" -ForegroundColor Yellow
$scriptExists = Test-Path $SCRIPT_PATH
Write-CheckResult "UPDATE script exists" $scriptExists $SCRIPT_PATH
$allPassed = $allPassed -and $scriptExists

# CHECK 2: Token configured
if ($scriptExists) {
    $content = Get-Content $SCRIPT_PATH -Raw
    $tokenConfigured = -not ($content -like '*YOUR_TOKEN_HERE*')
    Write-CheckResult "Token configured" $tokenConfigured
    $allPassed = $allPassed -and $tokenConfigured
}

# CHECK 3: Log directory
Write-Host ""
Write-Host "2. Log Configuration" -ForegroundColor Yellow
$logDir = Split-Path $LOG_PATH
$logDirExists = Test-Path $logDir
Write-CheckResult "Log directory exists" $logDirExists $logDir
$allPassed = $allPassed -and $logDirExists

# CHECK 4: Task Scheduler
Write-Host ""
Write-Host "3. Task Scheduler" -ForegroundColor Yellow
$task = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
$taskExists = $null -ne $task
Write-CheckResult "Task registered" $taskExists $TASK_NAME

if ($taskExists) {
    $taskEnabled = $task.State -eq "Ready"
    Write-CheckResult "Task enabled" $taskEnabled "State: $($task.State)"
    $allPassed = $allPassed -and $taskEnabled
}
else {
    $allPassed = $false
}

# CHECK 5: Network Connectivity
Write-Host ""
Write-Host "4. Network Connectivity" -ForegroundColor Yellow

$currentIP = Get-PublicIP
if ($null -ne $currentIP) {
    Write-CheckResult "Public IP accessible" $true "Your IP: $currentIP"
}
else {
    Write-CheckResult "Public IP accessible" $false "Cannot reach api.ipify.org"
    $allPassed = $false
}

# CHECK 6: DNS Resolution
Write-Host ""
Write-Host "5. DNS Resolution" -ForegroundColor Yellow

$dnsIP = Get-DNSResolvedIP
if ($null -ne $dnsIP) {
    Write-CheckResult "DNS resolves" $true "$DOMAIN.duckdns.org -> $dnsIP"

    if ($null -ne $currentIP) {
        $dnsMatch = $dnsIP -eq $currentIP
        Write-CheckResult "DNS matches current IP" $dnsMatch "Expected: $currentIP, Got: $dnsIP"

        if (-not $dnsMatch) {
            Write-Host ""
            Write-Host "WARNING: DNS tidak sesuai dengan current IP" -ForegroundColor Yellow
            Write-Host "Solusi: Jalankan UPDATE_DUCKDNS_IP.ps1 sekarang"
        }
    }
}
else {
    Write-CheckResult "DNS resolves" $false "Cannot resolve $DOMAIN.duckdns.org"
    $allPassed = $false
}

# CHECK 7: Log history
Write-Host ""
Write-Host "6. Recent Activity" -ForegroundColor Yellow

if (Test-Path $LOG_PATH) {
    $logSize = (Get-Item $LOG_PATH).Length
    Write-CheckResult "Log file exists" $true "Size: $([math]::Round($logSize/1KB, 2)) KB"

    $recentLogs = Get-Content $LOG_PATH -Tail 5 -ErrorAction SilentlyContinue
    if ($recentLogs) {
        Write-Host ""
        Write-Host "Last 5 log entries:" -ForegroundColor Gray
        $recentLogs | ForEach-Object { Write-Host "  $_" -ForegroundColor Gray }
    }
}
else {
    Write-Host "  (Log file not created yet - will be created on first run)"
}

# SUMMARY

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
if ($allPassed) {
    Write-Host "OK: Setup is complete and working!" -ForegroundColor Green
}
else {
    Write-Host "WARNING: Fix issues above" -ForegroundColor Yellow
}
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

if ($allPassed) {
    Write-Host "Next steps:"
    Write-Host "1. DuckDNS task akan otomatis berjalan setiap 5 menit"
    Write-Host "2. Pantau log file: $LOG_PATH"
    Write-Host "3. Jika IP berubah, domain akan terupdate otomatis"
    Write-Host ""
    Write-Host "Testing Task Scheduler (optional):"
    Write-Host "  Start-ScheduledTask -TaskName '$TASK_NAME'"
    Write-Host ""
}

Write-Host ""
