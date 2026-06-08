param(
    [string]$Queues = 'imports-high,default,reports-low',
    [int]$Tries = 1,
    [int]$Timeout = 0,
    [int]$Sleep = 1,
    [int]$Memory = 1024,
    [int]$MaxJobs = 25,
    [int]$MaxTimeSeconds = 3600,
    [int]$RestartDelaySeconds = 3,
    [string]$WorkerName = ''
)

$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

Write-Host "Persistent queue worker started."
if (-not [string]::IsNullOrWhiteSpace($WorkerName)) {
Write-Host "Worker name: $WorkerName"
}
Write-Host "Queues: $Queues"
Write-Host "Memory limit: ${Memory}MB"
Write-Host "Max jobs before recycle: $MaxJobs"
Write-Host "Max runtime before recycle: ${MaxTimeSeconds}s"
Write-Host "Press Ctrl+C to stop."
Write-Host ""

function Get-RestartJitterSeconds {
    param([string]$Name)

    if ([string]::IsNullOrWhiteSpace($Name)) {
        return 0
    }

    $sum = 0
    foreach ($char in $Name.ToCharArray()) {
        $sum += [int][char]$char
    }

    return $sum % 6
}

while ($true) {
    $startedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    Write-Host "[$startedAt] Starting queue worker..."

    $workerArgs = @(
        'artisan',
        'queue:work',
        "--queue=$Queues",
        "--tries=$Tries",
        "--timeout=$Timeout",
        "--sleep=$Sleep",
        "--memory=$Memory"
    )

    if ($MaxJobs -gt 0) {
        $workerArgs += "--max-jobs=$MaxJobs"
    }

    if ($MaxTimeSeconds -gt 0) {
        $workerArgs += "--max-time=$MaxTimeSeconds"
    }

    & php @workerArgs

    $exitCode = if ($null -eq $LASTEXITCODE) { 0 } else { [int] $LASTEXITCODE }
    $endedAt = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $jitterSeconds = Get-RestartJitterSeconds -Name $WorkerName
    $delaySeconds = [Math]::Max(1, $RestartDelaySeconds + $jitterSeconds)

    if ($exitCode -eq 0) {
        Write-Host "[$endedAt] Queue worker exited normally. Restarting in ${delaySeconds}s..."
    } else {
        Write-Warning "[$endedAt] Queue worker exited with code $exitCode. Restarting in ${delaySeconds}s..."
    }

    Start-Sleep -Seconds $delaySeconds
}
