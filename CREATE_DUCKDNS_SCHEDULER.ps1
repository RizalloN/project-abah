# DuckDNS Automatic Task Scheduler Setup
# Script ini mendaftarkan UPDATE_DUCKDNS_IP.ps1 ke Windows Task Scheduler
# agar berjalan otomatis setiap 5 menit tanpa intervensi manual
# PENTING: Jalankan sebagai Administrator!
# Run: powershell -ExecutionPolicy Bypass -File CREATE_DUCKDNS_SCHEDULER.ps1

# CONFIGURATION
$TASK_NAME = "DuckDNS-AutoUpdate"
$TASK_DESCRIPTION = "Automatic DuckDNS IP update every 5 minutes when IP changes"
$SCRIPT_PATH = "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
$LOG_PATH = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns.log"

# HELPER FUNCTIONS

function Write-ColorOutput {
    param([string]$message, [string]$color = "White")
    Write-Host $message -ForegroundColor $color
}

function Test-IsAdmin {
    $currentUser = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($currentUser)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-ScriptExists {
    return (Test-Path $SCRIPT_PATH)
}

function Test-TokenConfigured {
    $content = Get-Content $SCRIPT_PATH -Raw
    return -not ($content -like '*YOUR_TOKEN_HERE*')
}

# MAIN EXECUTION

Write-Host ""
Write-ColorOutput "========================================" "Cyan"
Write-ColorOutput "  DuckDNS Task Scheduler Setup" "Cyan"
Write-ColorOutput "========================================" "Cyan"
Write-Host ""

# Check: Administrator
if (-not (Test-IsAdmin)) {
    Write-ColorOutput "ERROR: Script harus dijalankan sebagai Administrator!" "Red"
    Write-Host ""
    Write-Host "Cara menjalankan sebagai Administrator:"
    Write-Host "1. Buka PowerShell"
    Write-Host "2. Klik kanan -> Run as Administrator"
    Write-Host "3. Jalankan command ini:"
    Write-Host "   powershell -ExecutionPolicy Bypass -File ""D:\XAMPP\htdocs\project-ABAH\CREATE_DUCKDNS_SCHEDULER.ps1"""
    Write-Host ""
    exit 1
}

Write-ColorOutput "[+] Running as Administrator" "Green"

# Check: Script exists
if (-not (Test-ScriptExists)) {
    Write-ColorOutput "ERROR: Script tidak ditemukan: $SCRIPT_PATH" "Red"
    exit 1
}

Write-ColorOutput "[+] Update script found" "Green"

# Check: Token configured
if (-not (Test-TokenConfigured)) {
    Write-ColorOutput "ERROR: Token DuckDNS belum dikonfigurasi!" "Red"
    Write-Host ""
    Write-Host "Edit file: $SCRIPT_PATH"
    Write-Host "Cari baris dengan YOUR_TOKEN_HERE"
    Write-Host "Ganti dengan token Anda dari: https://www.duckdns.org/"
    Write-Host ""
    exit 1
}

Write-ColorOutput "[+] Token is configured" "Green"

# Check: Log directory
$logDir = Split-Path $LOG_PATH
if (-not (Test-Path $logDir)) {
    New-Item -ItemType Directory -Path $logDir -Force | Out-Null
    Write-ColorOutput "[+] Created log directory" "Green"
}
else {
    Write-ColorOutput "[+] Log directory exists" "Green"
}

Write-Host ""
Write-Host "Setting up Task Scheduler task..."
Write-Host ""

# Remove existing task if exists
$existingTask = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
if ($null -ne $existingTask) {
    Write-Host "Found existing task, removing..."
    Unregister-ScheduledTask -TaskName $TASK_NAME -Confirm:$false -ErrorAction SilentlyContinue
    Start-Sleep -Seconds 1
}

# Create task trigger (every 5 minutes)
$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date)
$trigger.Repetition.Duration = [timespan]::MaxValue

# Create task action
$action = New-ScheduledTaskAction -Execute "powershell.exe" -Argument "-NoProfile -ExecutionPolicy Bypass -File `"$SCRIPT_PATH`""

# Create task settings
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -RunOnlyIfNetworkAvailable

# Register the task
try {
    Register-ScheduledTask -TaskName $TASK_NAME -Action $action -Trigger $trigger -Settings $settings -Description $TASK_DESCRIPTION -Force -ErrorAction Stop | Out-Null
    Write-ColorOutput "[+] Task registered successfully!" "Green"
}
catch {
    Write-ColorOutput "ERROR: Failed to register task: $_" "Red"
    exit 1
}

Write-Host ""

# Verify task creation
$task = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
if ($null -ne $task) {
    Write-ColorOutput "[+] Verification: Task exists in Task Scheduler" "Green"
    Write-Host ""
    Write-Host "Task Details:"
    Write-Host "  Name: $TASK_NAME"
    Write-Host "  Status: Enabled"
    Write-Host "  Trigger: Every 5 minutes"
    Write-Host "  Action: Update DuckDNS IP automatically"
    Write-Host "  Log: $LOG_PATH"
}
else {
    Write-ColorOutput "ERROR: Task verification failed" "Red"
    exit 1
}

Write-Host ""
Write-ColorOutput "========================================" "Cyan"
Write-ColorOutput "Setup Complete!" "Cyan"
Write-ColorOutput "========================================" "Cyan"
Write-Host ""

Write-Host "Apa yang terjadi sekarang:"
Write-Host "[+] DuckDNS update akan berjalan setiap 5 menit otomatis"
Write-Host "[+] Jika IP berubah, domain akan terupdate dalam 5 menit"
Write-Host "[+] Semua aktivitas dicatat di: $LOG_PATH"
Write-Host ""

Write-Host "Untuk memverifikasi setup:"
Write-Host "1. Buka Windows Task Scheduler"
Write-Host "2. Cari task: $TASK_NAME"
Write-Host "3. Klik kanan -> Run untuk menjalankan segera (testing)"
Write-Host ""

Write-Host "Untuk melihat log:"
Write-Host "  Get-Content ""$LOG_PATH"" -Tail 20"
Write-Host ""

Write-Host "Untuk stop otomasi (jika diperlukan):"
Write-Host "  Disable-ScheduledTask -TaskName ""$TASK_NAME"""
Write-Host ""

Write-Host "Untuk remove otomasi sepenuhnya:"
Write-Host "  Unregister-ScheduledTask -TaskName ""$TASK_NAME"" -Confirm:`$false"
Write-Host ""

Write-ColorOutput "OK: DuckDNS automation is now ACTIVE!" "Green"
Write-Host ""
