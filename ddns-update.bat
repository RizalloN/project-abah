@echo off
REM DuckDNS IP Update - Wrapper untuk Composer
REM Usage: composer ddns:update

setlocal enabledelayedexpansion

set SCRIPT_DIR=%~dp0
set SCRIPT_PATH=%SCRIPT_DIR%UPDATE_DUCKDNS_SIMPLE.ps1

REM Detect PHP
if exist "D:\xampp\php\php.exe" (
    set PHP_EXE=D:\xampp\php\php.exe
) else (
    for /f "delims=" %%A in ('where php.exe') do set PHP_EXE=%%A
)

if not defined PHP_EXE (
    echo ERROR: PHP not found
    exit /b 1
)

REM Run PowerShell script with proper encoding
powershell -NoProfile -ExecutionPolicy Bypass -File "%SCRIPT_PATH%"
exit /b %ERRORLEVEL%
