@echo off
REM Start DuckDNS IP Change Monitor
REM Run as Administrator for best results

echo ====================================
echo DuckDNS IP Monitor Starter
echo ====================================
echo.

REM Check if PowerShell is available
powershell -NoProfile -Command "Write-Host 'PowerShell OK'" >nul 2>&1
if errorlevel 1 (
    echo ERROR: PowerShell not found!
    echo Please install PowerShell or run this file manually
    pause
    exit /b 1
)

REM Create logs directory
if not exist "D:\XAMPP\htdocs\project-ABAH\logs" (
    mkdir "D:\XAMPP\htdocs\project-ABAH\logs"
    echo Created logs directory
)

echo.
echo Starting IP Monitor...
echo This will:
echo  1. Track your public IP changes
echo  2. Verify DuckDNS updates
echo  3. Check project accessibility
echo.

REM Run PowerShell script
powershell -NoProfile -ExecutionPolicy Bypass -File "D:\XAMPP\htdocs\project-ABAH\Monitor-IP-Changes.ps1"

if errorlevel 1 (
    echo.
    echo ERROR: Script failed to run
    echo Please check:
    echo  1. PowerShell execution policy (run as Admin)
    echo  2. Script path is correct
    echo  3. Internet connection available
)

pause
