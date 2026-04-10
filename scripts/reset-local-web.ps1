param(
    [string]$ProjectRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
)

$ErrorActionPreference = 'Stop'

$compiledViewPath = Join-Path $ProjectRoot 'storage\framework\cache\blade'
$legacyViewPath = Join-Path $ProjectRoot 'storage\framework\views'

foreach ($path in @($compiledViewPath, $legacyViewPath)) {
    if (-not (Test-Path -LiteralPath $path)) {
        continue
    }

    $resolved = (Resolve-Path -LiteralPath $path).Path
    if (-not $resolved.StartsWith($ProjectRoot, [System.StringComparison]::OrdinalIgnoreCase)) {
        throw "Refusing to clean path outside project root: $resolved"
    }

    Get-ChildItem -LiteralPath $resolved -Force | Where-Object {
        $_.Name -ne '.gitignore'
    } | Remove-Item -Recurse -Force
}

Push-Location $ProjectRoot
try {
    php artisan optimize:clear
} finally {
    Pop-Location
}

Write-Host 'Local web cache reset completed.' -ForegroundColor Green
