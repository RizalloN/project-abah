@echo off
REM Start Apache Service for Public Access
REM Run this as Administrator for best results

echo ====================================
echo Dashboard A-Six Server Starter
echo ====================================
echo.

cd /d D:\xampp\apache\bin

echo Checking Apache status...
tasklist | findstr /i httpd.exe >nul
if errorlevel 1 (
    echo Apache is NOT running. Starting Apache...
    start /b httpd.exe
    timeout /t 3 /nobreak
    echo.
    echo ✓ Apache started successfully!
) else (
    echo ✓ Apache is already running
)

echo.
echo ====================================
echo SERVER STATUS
echo ====================================
echo.

REM Display IP Configuration
echo Your Current IP:
ipconfig | findstr /i "IPv4"
echo.

echo Public URL: http://110.136.24.119
echo Local URL:  http://localhost
echo.

echo ====================================
echo Press any key to close...
pause >nul
