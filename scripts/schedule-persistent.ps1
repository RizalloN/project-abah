param(
    [int]$RestartDelaySeconds = 3,
    [string]$WorkerName = 'scheduler'
)

$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "Persistent scheduler started."
Write-Host "Worker name: $WorkerName"
Write-Host "Press Ctrl+C to stop."
Write-Host ""

while ($true) {
    $startedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "[$startedAt] Starting scheduler..."

    & php artisan system:scheduler-run --quiet --no-interaction

    $exitCode = if ($null -eq $LASTEXITCODE) { 0 } else { [int] $LASTEXITCODE }
    $endedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    $nextMinuteDelay = [Math]::Max(1, 60 - ([DateTimeOffset]::UtcNow.ToUnixTimeSeconds() % 60))
    if ($exitCode -eq 0) {
        Write-Host "[$endedAt] Scheduler tick selesai. Tick berikutnya dalam ${nextMinuteDelay}s..."
    } else {
        Write-Warning "[$endedAt] Scheduler tick keluar dengan kode $exitCode. Percobaan berikutnya dalam ${nextMinuteDelay}s..."
    }

    Start-Sleep -Seconds $nextMinuteDelay
}
