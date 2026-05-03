@echo off
REM DuckDNS Task Scheduler Setup - Run as Administrator
REM Right-click this file and select "Run as Administrator"

setlocal enabledelayedexpansion

echo.
echo ========================================
echo DuckDNS Automation Setup
echo ========================================
echo.

REM Check if running as admin
net session >nul 2>&1
if %errorLevel% neq 0 (
    echo ERROR: This script must be run as Administrator
    echo.
    echo Please:
    echo 1. Right-click on this file
    echo 2. Select "Run as Administrator"
    echo 3. Click "Yes" when prompted
    echo.
    pause
    exit /b 1
)

echo [OK] Running as Administrator
echo.

REM Run PowerShell script to setup task scheduler
powershell -NoProfile -ExecutionPolicy Bypass -Command ^
 "$SCRIPT_PATH = 'D:\XAMPP\htdocs\project-ABAH\UPDATE_DUCKDNS_IP.ps1'; " ^
 "$TASK_NAME = 'DuckDNS-AutoUpdate'; " ^
 "$TASK_DESC = 'Automatic DuckDNS IP update every 5 minutes'; " ^
 " " ^
 "Write-Host 'Creating Task Scheduler job...' -ForegroundColor Cyan; " ^
 " " ^
 "$trigger = New-ScheduledTaskTrigger -RepetitionInterval (New-TimeSpan -Minutes 5) -Once -At (Get-Date); " ^
 "$trigger.Repetition.Duration = [timespan]::MaxValue; " ^
 " " ^
 "$action = New-ScheduledTaskAction -Execute 'powershell.exe' " ^
     "-Argument '-ExecutionPolicy Bypass -File `"$SCRIPT_PATH`"' " ^
     "-WorkingDirectory (Split-Path -Parent $SCRIPT_PATH); " ^
 " " ^
 "$settings = New-ScheduledTaskSettingsSet " ^
     "-AllowStartIfOnBatteries " ^
     "-DontStopIfGoingOnBatteries " ^
     "-StartWhenAvailable " ^
     "-RunOnlyIfNetworkAvailable " ^
     "-MultipleInstances IgnoreNew; " ^
 " " ^
 "Register-ScheduledTask -TaskName $TASK_NAME " ^
     "-Action $action " ^
     "-Trigger $trigger " ^
     "-Settings $settings " ^
     "-Description $TASK_DESC " ^
     "-Force | Out-Null; " ^
 " " ^
 "Write-Host '✓ Task registered successfully!' -ForegroundColor Green; " ^
 "Write-Host ''; " ^
 "Write-Host 'Task Details:' -ForegroundColor Cyan; " ^
 "Write-Host '  Name: '$TASK_NAME -ForegroundColor Green; " ^
 "Write-Host '  Script: '$SCRIPT_PATH -ForegroundColor Green; " ^
 "Write-Host '  Frequency: Every 5 minutes' -ForegroundColor Green; " ^
 "Write-Host '  Log: D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log' -ForegroundColor Green; " ^
 "Write-Host ''; " ^
 "Write-Host 'Next Steps:' -ForegroundColor Cyan; " ^
 "Write-Host '1. Open Task Scheduler (Win+R -> taskschd.msc)' -ForegroundColor White; " ^
 "Write-Host '2. Find: DuckDNS-AutoUpdate' -ForegroundColor White; " ^
 "Write-Host '3. Right-click -> Run (to test)' -ForegroundColor White; " ^
 "Write-Host '4. Check logs in 30 seconds:' -ForegroundColor White; " ^
 "Write-Host '   Get-Content D:\XAMPP\htdocs\project-ABAH\logs\duckdns_update.log -Tail 5' -ForegroundColor Yellow; "

echo.
echo ========================================
echo Setup Complete!
echo ========================================
echo.
echo Task Scheduler job 'DuckDNS-AutoUpdate' has been created.
echo It will run automatically every 5 minutes.
echo.
pause
