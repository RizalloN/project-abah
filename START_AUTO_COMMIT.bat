@echo off
setlocal

set "ROOT=%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -NoExit -File "%ROOT%scripts\auto-commit.ps1" %*
