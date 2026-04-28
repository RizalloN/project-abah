@echo off
REM Update IP Configuration in .env if IP changes
REM Run this script if your IP address has changed

setlocal enabledelayedexpansion

cls
echo ====================================
echo IP Configuration Updater
echo Dashboard A-Six Public Access
echo ====================================
echo.

REM Get current IP
for /f "tokens=13" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do set "IP=%%a"

echo Current IPv4 Address: %IP%
echo.

REM Read current .env
for /f "tokens=2 delims==" %%a in ('findstr /i "APP_URL=" .env') do set "CURRENT_URL=%%a"
echo Current APP_URL in .env: %CURRENT_URL%
echo.

REM Offer to update
set /p UPDATE="Do you want to update APP_URL to http://%IP%? (Y/N): "
if /i "%UPDATE%"=="Y" (
    REM Backup .env
    copy .env .env.backup >nul
    echo ✓ Backup created: .env.backup

    REM Update .env
    powershell -Command "(Get-Content .env) -replace 'APP_URL=.*', 'APP_URL=http://%IP%' | Set-Content .env"

    REM Update Apache config
    powershell -Command "(Get-Content D:\xampp\apache\conf\extra\httpd-vhosts.conf) -replace 'ServerName [0-9.]+', 'ServerName %IP%' | Set-Content D:\xampp\apache\conf\extra\httpd-vhosts.conf"

    echo.
    echo ✓ Configuration updated successfully!
    echo   - APP_URL: http://%IP%
    echo   - Apache ServerName: %IP%
    echo.
    echo   Please restart Apache for changes to take effect.
) else (
    echo.
    echo No changes made.
)

echo.
echo Press any key to close...
pause >nul
