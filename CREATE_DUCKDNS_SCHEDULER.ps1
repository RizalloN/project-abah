# DuckDNS Automatic Task Scheduler Setup
# Script ini mendaftarkan UPDATE_DUCKDNS_IP.ps1 ke Windows Task Scheduler
# PENTING: Jalankan sebagai Administrator!

$ErrorActionPreference = 'Stop'

# Configuration
$TASK_NAME = "DuckDNS-AutoUpdate"
$TASK_DESCRIPTION = "Automatic DuckDNS IP update every 5 minutes"
$SCRIPT_PATH = "D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1"
$LOG_PATH = "D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log"

function Write-ColorOutput {
    param([string]$message, [string]$color = "White")
    Write-Host $message -ForegroundColor $color
}

function Test-IsAdmin {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = New-Object Security.Principal.WindowsPrincipal($identity)
    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Invoke-TaskSchedulerSetup {
    Write-Host ""
    Write-ColorOutput "========================================" "Cyan"
    Write-ColorOutput "  DuckDNS Task Scheduler Setup" "Cyan"
    Write-ColorOutput "========================================" "Cyan"
    Write-Host ""

    # Validate admin
    if (-not (Test-IsAdmin)) {
        Write-ColorOutput "ERROR: Must run as Administrator" "Red"
        exit 1
    }
    Write-ColorOutput "[+] Running as Administrator" "Green"

    # Validate script exists
    if (-not (Test-Path $SCRIPT_PATH -PathType Leaf)) {
        Write-ColorOutput "ERROR: Script not found: $SCRIPT_PATH" "Red"
        exit 1
    }
    Write-ColorOutput "[+] Update script found" "Green"

    # Ensure log directory exists
    $logDir = Split-Path -Parent $LOG_PATH
    if (-not (Test-Path $logDir -PathType Container)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
        Write-ColorOutput "[+] Created log directory" "Green"
    } else {
        Write-ColorOutput "[+] Log directory exists" "Green"
    }

    Write-Host ""
    Write-Host "Setting up scheduled task..."
    Write-Host ""

    # Remove existing task
    $existingTask = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
    if ($null -ne $existingTask) {
        Write-Host "[*] Removing existing task..."
        Unregister-ScheduledTask -TaskName $TASK_NAME -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
        Start-Sleep -Milliseconds 500
    }

    # Build PowerShell command with proper escaping
    $psCommand = "powershell.exe"
    $psArgs = "-NoProfile -ExecutionPolicy Bypass -File `"$SCRIPT_PATH`""
    $workDir = Split-Path -Parent $SCRIPT_PATH

    try {
        # Create trigger for 5-minute interval starting now
        $now = Get-Date
        $trigger = New-ScheduledTaskTrigger `
            -Once `
            -At $now `
            -RepetitionInterval (New-TimeSpan -Minutes 5)

        # Configure trigger for infinite repetition (no duration end)
        # This avoids the duration validation issue
        $trigger.Repetition.StopAtDurationEnd = $false

        # Create action
        $action = New-ScheduledTaskAction `
            -Execute $psCommand `
            -Argument $psArgs `
            -WorkingDirectory $workDir

        # Create settings with Windows Task Scheduler best practices
        $settings = New-ScheduledTaskSettingsSet `
            -AllowStartIfOnBatteries `
            -DontStopIfGoingOnBatteries `
            -StartWhenAvailable `
            -RunOnlyIfNetworkAvailable `
            -MultipleInstances IgnoreNew `
            -ExecutionTimeLimit (New-TimeSpan -Hours 1)

        # Register the task with SYSTEM principal (highest privilege)
        $principal = New-ScheduledTaskPrincipal `
            -UserId "SYSTEM" `
            -RunLevel Highest

        # Register task
        Register-ScheduledTask `
            -TaskName $TASK_NAME `
            -Description $TASK_DESCRIPTION `
            -Trigger $trigger `
            -Action $action `
            -Settings $settings `
            -Principal $principal `
            -Force `
            -ErrorAction Stop | Out-Null

        Write-ColorOutput "[+] Task registered successfully!" "Green"

        # Verify creation
        $task = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
        if ($null -ne $task) {
            Write-ColorOutput "[+] Verification: Task created in Task Scheduler" "Green"
            Write-Host ""
            Write-Host "Task Details:"
            Write-Host "  Name: $TASK_NAME"
            Write-Host "  Status: $(if ($task.State -eq 'Ready') { 'Enabled' } else { $task.State })"
            Write-Host "  Description: $TASK_DESCRIPTION"
            Write-Host "  Schedule: Every 5 minutes"
            Write-Host "  Log File: $LOG_PATH"
            Write-Host ""
        } else {
            throw "Task verification failed"
        }

    } catch {
        Write-ColorOutput "ERROR: Failed to register task" "Red"
        Write-Host "Details: $_"
        exit 1
    }

    # Display completion info
    Write-Host ""
    Write-ColorOutput "========================================" "Cyan"
    Write-ColorOutput "Setup Complete!" "Cyan"
    Write-ColorOutput "========================================" "Cyan"
    Write-Host ""

    Write-Host "DuckDNS automation is now ACTIVE:"
    Write-Host "  [+] Runs every 5 minutes automatically"
    Write-Host "  [+] Monitors and updates IP changes"
    Write-Host "  [+] Logs activity to: $LOG_PATH"
    Write-Host ""

    Write-Host "Useful commands:"
    Write-Host "  Monitor logs:"
    Write-Host "    Get-Content ""$LOG_PATH"" -Tail 20 -Wait"
    Write-Host ""
    Write-Host "  Disable task temporarily:"
    Write-Host "    Disable-ScheduledTask -TaskName ""$TASK_NAME"""
    Write-Host ""
    Write-Host "  Enable task:"
    Write-Host "    Enable-ScheduledTask -TaskName ""$TASK_NAME"""
    Write-Host ""
    Write-Host "  Remove task permanently:"
    Write-Host "    Unregister-ScheduledTask -TaskName ""$TASK_NAME"" -Confirm:`$false"
    Write-Host ""
    Write-Host "  Run task immediately (for testing):"
    Write-Host "    Start-ScheduledTask -TaskName ""$TASK_NAME"""
    Write-Host ""

    Write-ColorOutput "OK: Ready for production" "Green"
    Write-Host ""
}

# Execute
try {
    Invoke-TaskSchedulerSetup
} catch {
    Write-ColorOutput "Fatal error: $_" "Red"
    exit 1
}
