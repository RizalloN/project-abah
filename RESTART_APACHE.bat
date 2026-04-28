@echo off
REM Restart Apache as Administrator
REM This file must be run as Administrator

echo ====================================
echo Apache Service Restart
echo ====================================
echo.

REM Kill existing Apache processes
echo Stopping Apache processes...
taskkill /F /IM httpd.exe /T 2>nul
timeout /t 2 /nobreak

REM Start Apache
echo.
echo Starting Apache...
cd /d D:\xampp\apache\bin
start /b httpd.exe
timeout /t 3 /nobreak

REM Verify
echo.
echo ====================================
echo VERIFICATION
echo ====================================
echo.

tasklist | findstr httpd.exe
if errorlevel 1 (
    echo ✗ Apache FAILED to start - Check error logs
    echo Log location: D:\xampp\apache\logs\error.log
) else (
    echo ✓ Apache successfully restarted
    echo.
    echo New Configuration:
    echo - Domain: asixdashboard.duckdns.org
    echo - IP: 110.136.24.119
    echo - Project: Dashboard A-Six
    echo.
    echo Test URL: http://asixdashboard.duckdns.org
)

echo.
pause
