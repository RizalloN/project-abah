@echo off
cd /d "%~dp0"
D:\xampp\php\php.exe artisan system:scheduler-run >> logs\scheduler.log 2>&1
