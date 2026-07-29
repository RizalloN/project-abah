param(
    [Parameter(Mandatory = $true)][string]$PhpExecutable,
    [Parameter(Mandatory = $true)][string]$ArtisanPath,
    [Parameter(Mandatory = $true)][string]$WorkingDirectory,
    [Parameter(Mandatory = $true)][string]$Queues,
    [Parameter(Mandatory = $true)][string]$Timeout,
    [Parameter(Mandatory = $true)][string]$Memory,
    [Parameter(Mandatory = $true)][string]$Sleep,
    [Parameter(Mandatory = $true)][string]$OutputLog,
    [Parameter(Mandatory = $true)][string]$ErrorLog,
    [int]$MaxJobs = 0,
    [int]$MaxTime = 0
)

$workerArguments = @(
    $ArtisanPath,
    'queue:work',
    "--queue=$Queues",
    "--timeout=$Timeout",
    "--memory=$Memory",
    "--sleep=$Sleep"
)

if ($MaxJobs -gt 0) {
    $workerArguments += "--max-jobs=$MaxJobs"
}

if ($MaxTime -gt 0) {
    $workerArguments += "--max-time=$MaxTime"
}

$worker = Start-Process `
    -FilePath $PhpExecutable `
    -ArgumentList $workerArguments `
    -WorkingDirectory $WorkingDirectory `
    -WindowStyle Hidden `
    -RedirectStandardOutput $OutputLog `
    -RedirectStandardError $ErrorLog `
    -PassThru `
    -Wait

exit $worker.ExitCode
