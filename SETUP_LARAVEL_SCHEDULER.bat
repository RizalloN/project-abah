@echo off
:: Check for Administrator privileges
net session >nul 2>&1
if %errorLevel% == 0 (
    goto :admin
) else (
    goto :elevate
)

:elevate
echo ====================================================
echo Requesting Administrator privileges...
echo ====================================================
powershell -Command "Start-Process -FilePath '%0' -Verb RunAs"
exit /b

:admin
echo ====================================================
echo Setup Laravel Auto-Scheduler (Self-Healing)
echo ====================================================
echo.

cd /d "%~dp0"
powershell -NoProfile -ExecutionPolicy Bypass -File "CREATE_LARAVEL_SCHEDULER.ps1"

echo.
echo Press any key to exit...
pause >nul
