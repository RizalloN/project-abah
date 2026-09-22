@echo off
title Laravel Queue Worker - Project ABAH
cd /d "%~dp0"

echo ====================================================
echo   Laravel Persistent Queue Worker - Project ABAH
echo ====================================================
echo Priority queues: imports-high, imports-daily-loan
echo Background queues: default, reports-low, remote-sources, snapshots-priority, snapshots-parallel, shadow-backfill
echo.
echo ====================================================
echo Menjalankan Queue Worker Manager (Self-Healing, Staleproof)...
echo Syntax: php artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30
echo ====================================================
echo.

"D:\xampp\php\php.exe" artisan queue:ensure-running --timeout=0 --memory=512 --max-jobs=25 --max-time=3600 --check-interval=30

pause
