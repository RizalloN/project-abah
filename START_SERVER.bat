@echo off
setlocal enabledelayedexpansion

set "PROJECT_DIR=D:\XAMPP\htdocs\project-ABAH"
set "APACHE_BIN=D:\xampp\apache\bin"
set "PUBLIC_DOMAIN=asixdashboard.online"
set "PUBLIC_HTTPS=https://%PUBLIC_DOMAIN%"

echo ====================================
echo Dashboard A-Six Server Starter
echo ====================================
echo.

cd /d "%APACHE_BIN%"

echo Checking Apache status...
tasklist | findstr /i "httpd.exe" >nul
if errorlevel 1 (
    echo Apache is NOT running. Starting Apache...
    start /b httpd.exe
    timeout /t 3 /nobreak >nul
    echo [OK] Apache start command sent.
) else (
    echo [OK] Apache is already running.
)

echo.
echo ====================================
echo SERVER STATUS
echo ====================================
echo.

echo Local network IP:
ipconfig | findstr /i "IPv4"
echo.

echo Public URL HTTPS: %PUBLIC_HTTPS%
echo Local URL       : http://localhost
echo.
echo Public access now uses Cloudflare Tunnel. Keep router port forwarding 80/443/3389 disabled.
echo.
echo ====================================
echo Press any key to close...
pause >nul
