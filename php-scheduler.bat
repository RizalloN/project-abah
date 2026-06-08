@echo off
cd /d "%~dp0"
D:\xampp\php\php.exe artisan schedule:run >> logs\scheduler.log 2>&1
