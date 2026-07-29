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

    & php artisan schedule:work --quiet --no-interaction

    $exitCode = if ($null -eq $LASTEXITCODE) { 0 } else { [int] $LASTEXITCODE }
    $endedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    if ($exitCode -eq 0) {
        Write-Host "[$endedAt] Scheduler exited normally. Restarting in ${RestartDelaySeconds}s..."
    } else {
        Write-Warning "[$endedAt] Scheduler exited with code $exitCode. Restarting in ${RestartDelaySeconds}s..."
    }

    Start-Sleep -Seconds ([Math]::Max(1, $RestartDelaySeconds))
}
