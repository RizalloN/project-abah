@echo off
setlocal enabledelayedexpansion

REM Determine the directory where this script is located
set SCRIPT_DIR=%~dp0

REM Try to use global PHP first, then fallback to XAMPP PHP
where /q php.exe
if errorlevel 1 (
    set PHP_EXE=D:\xampp\php\php.exe
) else (
    for /f "delims=" %%A in ('where php.exe') do set PHP_EXE=%%A
)

REM Determine Composer PHAR location
if exist "C:\ProgramData\ComposerSetup\bin\composer.phar" (
    set COMPOSER_PHAR=C:\ProgramData\ComposerSetup\bin\composer.phar
) else if exist "%SCRIPT_DIR%composer.phar" (
    set COMPOSER_PHAR=%SCRIPT_DIR%composer.phar
) else (
    echo ERROR: Composer PHAR not found
    exit /b 1
)

REM Execute Composer with PHP
"%PHP_EXE%" "%COMPOSER_PHAR%" %*
