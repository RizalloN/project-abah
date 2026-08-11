@echo off
setlocal
cd /d "%~dp0"

if not exist "storage\logs" mkdir "storage\logs"

D:\xampp\php\php.exe artisan database:backup-daily %* >> "storage\logs\daily-database-backup.log" 2>&1
set "DAILY_BACKUP_EXIT_CODE=%ERRORLEVEL%"

endlocal & exit /b %DAILY_BACKUP_EXIT_CODE%
