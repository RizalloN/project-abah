# Laravel Automatic Task Scheduler Setup
# Script ini mendaftarkan php-scheduler.bat ke Windows Task Scheduler
# PENTING: Jalankan sebagai Administrator!

$ErrorActionPreference = 'Stop'

# Task Configuration
$TASK_NAME = "Laravel-Scheduler-ProjectABAH"
$TASK_DESCRIPTION = "Automatic Laravel schedule runner every 1 minute for Project ABAH"
$PROJECT_DIR = $PSScriptRoot
if ([string]::IsNullOrEmpty($PROJECT_DIR)) {
    $PROJECT_DIR = "D:\XAMPP\htdocs\project-ABAH"
}
$SCRIPT_PATH = Join-Path $PROJECT_DIR "php-scheduler.bat"
$LOG_PATH = Join-Path $PROJECT_DIR "logs\scheduler.log"

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
    Write-ColorOutput "  Laravel Task Scheduler Setup" "Cyan"
    Write-ColorOutput "========================================" "Cyan"
    Write-Host ""

    # Validate admin
    if (-not (Test-IsAdmin)) {
        Write-ColorOutput "ERROR: Must run as Administrator to register scheduled tasks" "Red"
        exit 1
    }
    Write-ColorOutput "[+] Running as Administrator" "Green"

    # Validate script exists
    if (-not (Test-Path $SCRIPT_PATH -PathType Leaf)) {
        Write-ColorOutput "ERROR: Scheduler batch file not found at: $SCRIPT_PATH" "Red"
        exit 1
    }
    Write-ColorOutput "[+] Scheduler batch script found" "Green"

    # Ensure logs directory exists
    $logDir = Split-Path -Parent $LOG_PATH
    if (-not (Test-Path $logDir -PathType Container)) {
        New-Item -ItemType Directory -Path $logDir -Force | Out-Null
        Write-ColorOutput "[+] Created logs directory" "Green"
    }

    Write-Host ""
    Write-Host "Registering task in Windows Task Scheduler..."
    Write-Host ""

    # Remove existing task if exists
    $existingTask = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
    if ($null -ne $existingTask) {
        Write-Host "[*] Removing existing task $TASK_NAME..."
        Unregister-ScheduledTask -TaskName $TASK_NAME -Confirm:$false -ErrorAction SilentlyContinue | Out-Null
        Start-Sleep -Milliseconds 500
    }

    # Task triggers & actions
    $now = Get-Date
    # Create a trigger that repeats every 1 minute
    $trigger = New-ScheduledTaskTrigger `
        -Once `
        -At $now `
        -RepetitionInterval (New-TimeSpan -Minutes 1)

    # Disable StopAtDurationEnd so repetition runs infinitely
    $trigger.Repetition.StopAtDurationEnd = $false

    # Set command to cmd.exe running php-scheduler.bat
    $action = New-ScheduledTaskAction `
        -Execute "cmd.exe" `
        -Argument "/c `"$SCRIPT_PATH`"" `
        -WorkingDirectory $PROJECT_DIR

    # Set parameters
    $settings = New-ScheduledTaskSettingsSet `
        -AllowStartIfOnBatteries `
        -DontStopIfGoingOnBatteries `
        -StartWhenAvailable `
        -MultipleInstances IgnoreNew `
        -ExecutionTimeLimit (New-TimeSpan -Minutes 5)

    # Run as SYSTEM for background, silent and high privilege execution
    $principal = New-ScheduledTaskPrincipal `
        -UserId "SYSTEM" `
        -RunLevel Highest

    try {
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

        # Verify task creation
        $task = Get-ScheduledTask -TaskName $TASK_NAME -ErrorAction SilentlyContinue
        if ($null -ne $task) {
            Write-ColorOutput "[+] Verification: Task is active in Task Scheduler" "Green"
            Write-Host ""
            Write-Host "Task Details:"
            Write-Host "  Name: $TASK_NAME"
            Write-Host "  Status: $($task.State)"
            Write-Host "  Schedule: Every 1 minute"
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

    Write-Host ""
    Write-ColorOutput "========================================" "Cyan"
    Write-ColorOutput "Setup Complete!" "Cyan"
    Write-ColorOutput "========================================" "Cyan"
    Write-Host ""
    Write-Host "Laravel Scheduler is now automated. Stuck database queries and frozen queue workers will be repaired automatically every minute."
    Write-Host ""
}

try {
    Invoke-TaskSchedulerSetup
} catch {
    Write-ColorOutput "Fatal error: $_" "Red"
    exit 1
}
