@echo off
setlocal enabledelayedexpansion

set "PROJECT_DIR=D:\XAMPP\htdocs\project-ABAH"
set "APACHE_BIN=D:\xampp\apache\bin"
set "PUBLIC_DOMAIN=asixdashboard.duckdns.org"
set "PUBLIC_HTTPS=https://%PUBLIC_DOMAIN%"
set "PUBLIC_HTTP=http://%PUBLIC_DOMAIN%"

echo ====================================
echo Dashboard A-Six Server Starter
echo ====================================
echo.

echo Checking public access and DuckDNS before startup...
cd /d "%PROJECT_DIR%"
if exist "D:\xampp\php\php.exe" (
    "D:\xampp\php\php.exe" artisan network:public-health --fix --force
) else (
    php artisan network:public-health --fix --force
)
if errorlevel 1 (
    echo.
    echo [WARN] Public health check failed. Laravel scheduler will retry when running.
) else (
    echo.
    echo [OK] Public access and DuckDNS are healthy.
)

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
echo Public URL HTTP : %PUBLIC_HTTP%
echo Local URL       : http://localhost
echo.
echo If public access still fails from outside the office network:
echo - Confirm router port forwarding 80 and 443 targets this PC.
echo - Run SETUP_DUCKDNS_ADMIN.bat as Administrator once to keep DuckDNS updated automatically.
echo.
echo ====================================
echo Press any key to close...
pause >nul
