$ErrorActionPreference = 'Continue'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

& php artisan config:clear --ansi
if ($LASTEXITCODE -ne 0) {
    exit $LASTEXITCODE
}

& php artisan test
$testExitCode = if ($null -eq $LASTEXITCODE) { 1 } else { [int] $LASTEXITCODE }

& php artisan optimize --ansi
$optimizeExitCode = if ($null -eq $LASTEXITCODE) { 1 } else { [int] $LASTEXITCODE }

if ($testExitCode -ne 0) {
    exit $testExitCode
}

exit $optimizeExitCode
