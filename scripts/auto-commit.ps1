param(
    [int] $IntervalSeconds = 60,
    [int] $StableSeconds = 3,
    [string] $MessagePrefix = 'auto commit',
    [switch] $IncludeUntracked,
    [switch] $Push,
    [switch] $Once,
    [switch] $DryRun
)

$ErrorActionPreference = 'Stop'

$repoRoot = Resolve-Path (Join-Path $PSScriptRoot '..')
Set-Location $repoRoot

function Invoke-Git {
    param([Parameter(ValueFromRemainingArguments = $true)][string[]] $Arguments)

    $output = & git @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "git $($Arguments -join ' ') failed:`n$output"
    }

    return $output
}

function Get-GitStatusLines {
    if ($IncludeUntracked) {
        return @(Invoke-Git status --porcelain)
    }

    return @(Invoke-Git status --porcelain --untracked-files=no)
}

function Test-GitOperationInProgress {
    $gitDir = (Invoke-Git rev-parse --git-dir | Select-Object -First 1).Trim()
    $blockedFiles = @('MERGE_HEAD', 'REBASE_HEAD', 'CHERRY_PICK_HEAD', 'REVERT_HEAD')

    foreach ($file in $blockedFiles) {
        if (Test-Path (Join-Path $gitDir $file)) {
            return $true
        }
    }

    return $false
}

function Invoke-AutoCommitOnce {
    if (Test-GitOperationInProgress) {
        Write-Host '[auto-commit] Git operation in progress; skipping this cycle.'
        return
    }

    $before = Get-GitStatusLines
    if ($before.Count -eq 0) {
        Write-Host '[auto-commit] No tracked changes to commit.'
        return
    }

    Start-Sleep -Seconds $StableSeconds

    $after = Get-GitStatusLines
    $beforeText = $before -join "`n"
    $afterText = $after -join "`n"

    if ($beforeText -ne $afterText) {
        Write-Host '[auto-commit] Changes are still moving; waiting for the next cycle.'
        return
    }

    $timestamp = Get-Date -Format 'yyyy-MM-dd HH:mm:ss'
    $message = "${MessagePrefix}: $timestamp"

    if ($DryRun) {
        Write-Host "[auto-commit] Dry run. Would commit with message: $message"
        $after | ForEach-Object { Write-Host "  $_" }
        return
    }

    if ($IncludeUntracked) {
        Invoke-Git add --all | Out-Null
    } else {
        Invoke-Git add -u | Out-Null
    }

    & git diff --cached --quiet
    if ($LASTEXITCODE -eq 0) {
        Write-Host '[auto-commit] Nothing staged after filtering.'
        return
    }

    Invoke-Git commit -m $message | Out-Host

    if ($Push) {
        Invoke-Git push | Out-Host
    }
}

Write-Host "[auto-commit] Watching $repoRoot"
Write-Host "[auto-commit] Mode: $(if ($IncludeUntracked) { 'tracked + untracked' } else { 'tracked only' })"
Write-Host "[auto-commit] Interval: ${IntervalSeconds}s, stable wait: ${StableSeconds}s"

do {
    Invoke-AutoCommitOnce

    if ($Once) {
        break
    }

    Start-Sleep -Seconds $IntervalSeconds
} while ($true)
