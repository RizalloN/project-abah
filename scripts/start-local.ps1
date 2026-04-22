param(
    [switch]$WithVite,
    [switch]$OpenBrowser
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$importQueueLog = Join-Path $projectRoot 'storage\logs\local-import-queue-worker.log'
$reportQueueLog = Join-Path $projectRoot 'storage\logs\local-report-queue-worker.log'
$scheduleLog = Join-Path $projectRoot 'storage\logs\local-scheduler.log'

New-Item -ItemType Directory -Force -Path (Split-Path -Parent $importQueueLog) | Out-Null

function Test-ProcessCommand {
    param([string]$Pattern)

    try {
        $processes = Get-CimInstance Win32_Process -Filter "Name = 'php.exe'" -ErrorAction Stop

        foreach ($process in $processes) {
            $commandLine = $process.CommandLine
            if ($null -eq $commandLine) {
                $commandLine = ''
            }

            if ($commandLine -like "*$Pattern*") {
                return $true
            }
        }
    } catch {
        return $false
    }

    return $false
}

function Start-PhpBackground {
    param(
        [string]$Name,
        [string]$Arguments,
        [string]$LogPath,
        [string]$DetectPattern
    )

    if (Test-ProcessCommand -Pattern $DetectPattern) {
        Write-Host "$Name sudah berjalan."
        return
    }

    $command = "cd /d `"$projectRoot`" && php $Arguments >> `"$LogPath`" 2>&1"
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $command -WindowStyle Hidden | Out-Null
    Write-Host "$Name dijalankan. Log: $LogPath"
}

Start-PhpBackground `
    -Name 'Import queue worker' `
    -Arguments 'artisan queue:work --queue=imports-high --tries=1 --timeout=120 --sleep=1 --memory=256' `
    -LogPath $importQueueLog `
    -DetectPattern 'artisan queue:work --queue=imports-high'

Start-PhpBackground `
    -Name 'Report queue worker' `
    -Arguments 'artisan queue:work --queue=default,reports-low --tries=1 --timeout=120 --sleep=1 --memory=256' `
    -LogPath $reportQueueLog `
    -DetectPattern 'artisan queue:work --queue=default,reports-low'

Start-PhpBackground `
    -Name 'Laravel scheduler' `
    -Arguments 'artisan schedule:work' `
    -LogPath $scheduleLog `
    -DetectPattern 'artisan schedule:work'

if ($WithVite) {
    if (Get-Command npm.cmd -ErrorAction SilentlyContinue) {
        Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', "cd /d `"$projectRoot`" && npm.cmd run dev" | Out-Null
        Write-Host 'Vite dev server dijalankan.'
    } else {
        Write-Warning 'npm.cmd tidak ditemukan; Vite dilewati.'
    }
}

if ($OpenBrowser) {
    Start-Process 'http://localhost/project-ABAH/'
}

Write-Host 'Project lokal siap. Pastikan Apache dan MySQL XAMPP sudah menyala.'
