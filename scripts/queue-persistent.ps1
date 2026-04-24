param(
    [string]$Queues = 'imports-high,default,reports-low',
    [int]$Tries = 1,
    [int]$Timeout = 0,
    [int]$Sleep = 1,
    [int]$Memory = 1024,
    [int]$RestartDelaySeconds = 3
)

$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "Persistent queue worker started."
Write-Host "Queues: $Queues"
Write-Host "Memory limit: ${Memory}MB"
Write-Host "Press Ctrl+C to stop."
Write-Host ""

while ($true) {
    $startedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "[$startedAt] Starting queue worker..."

    & php artisan queue:work `
        --queue="$Queues" `
        --tries="$Tries" `
        --timeout="$Timeout" `
        --sleep="$Sleep" `
        --memory="$Memory"

    $exitCode = if ($null -eq $LASTEXITCODE) { 0 } else { [int] $LASTEXITCODE }
    $endedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'

    if ($exitCode -eq 0) {
        Write-Host "[$endedAt] Queue worker exited normally. Restarting in ${RestartDelaySeconds}s..."
    } else {
        Write-Warning "[$endedAt] Queue worker exited with code $exitCode. Restarting in ${RestartDelaySeconds}s..."
    }

    Start-Sleep -Seconds ([Math]::Max(1, $RestartDelaySeconds))
}
