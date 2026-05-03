param(
    [switch]$IncludeLegacy
)

$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
$escapedRoot = [regex]::Escape($projectRoot)
$patterns = @(
    'queue-persistent\.ps1',
    'schedule-persistent\.ps1',
    'abah-.*-worker',
    'abah-scheduler'
)

if ($IncludeLegacy) {
    $patterns += @(
        'artisan\s+queue:work',
        'artisan\s+schedule:work'
    )
}

$processes = Get-CimInstance Win32_Process |
    Where-Object {
        $commandLine = $_.CommandLine
        if ([string]::IsNullOrWhiteSpace($commandLine)) {
            return $false
        }

        $isProjectProcess = $commandLine -match $escapedRoot
        $isLegacyLaravelWorker = $IncludeLegacy -and (
            $commandLine -match 'artisan\s+queue:work' -or
            $commandLine -match 'artisan\s+schedule:work'
        )

        if (-not $isProjectProcess -and -not $isLegacyLaravelWorker) {
            return $false
        }

        foreach ($pattern in $patterns) {
            if ($commandLine -match $pattern) {
                return $true
            }
        }

        return $false
    }

if (-not $processes) {
    Write-Host 'Tidak ada worker power yang sedang berjalan.'
    exit 0
}

$stopped = 0
foreach ($process in $processes) {
    try {
        Stop-Process -Id $process.ProcessId -Force -ErrorAction Stop
        $stopped++
        Write-Host "Stopped PID $($process.ProcessId): $($process.Name)"
    } catch {
        Write-Warning "Gagal stop PID $($process.ProcessId): $($_.Exception.Message)"
    }
}

Write-Host "Selesai. Total proses dihentikan: $stopped"
