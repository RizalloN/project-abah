@echo off
title Laravel Queue Worker - Project ABAH
cd /d "%~dp0"

echo ====================================================
echo   Laravel Persistent Queue Worker - Project ABAH
echo ====================================================
echo Priority queues: imports-high, imports-daily-loan
echo Background queues: default, reports-low, remote-sources, snapshots-priority, snapshots-parallel, shadow-backfill
echo.
echo Worker siap menerima job import kapan saja.
echo Tekan Ctrl+C untuk menghentikan worker.
echo ====================================================
echo.

:loop
echo [%date% %time%] Queue worker berjalan...
"D:\xampp\php\php.exe" artisan queue:work --queue=imports-high,imports-daily-loan,default,reports-low,remote-sources,snapshots-priority,snapshots-parallel,shadow-backfill --tries=1 --timeout=0 --sleep=1 --memory=512
echo.
echo [%date% %time%] Worker berhenti/recycle. Me-restart dalam 3 detik...
timeout /t 3 /nobreak >nul
goto loop
