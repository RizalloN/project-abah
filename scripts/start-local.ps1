param(
    [switch]$WithVite,
    [switch]$OpenBrowser,
    [Nullable[int]]$ImportWorkers = $null,
    [Nullable[int]]$ReportWorkers = $null,
    [Nullable[int]]$SnapshotWorkers = $null,
    [Nullable[int]]$ShadowWorkers = $null,
    [Nullable[int]]$WorkerMemory = $null,
    [Nullable[int]]$WorkerMaxJobs = $null,
    [Nullable[int]]$WorkerMaxTime = $null,
    [switch]$SkipQueueRestart
)

$ErrorActionPreference = 'Stop'

$projectRoot = Split-Path -Parent $PSScriptRoot
Set-Location $projectRoot

$scheduleLog = Join-Path $projectRoot 'storage\logs\persistent-scheduler.log'

New-Item -ItemType Directory -Force -Path (Split-Path -Parent $scheduleLog) | Out-Null

function Get-IntSetting {
    param(
        [Nullable[int]]$Value,
        [string]$EnvName,
        [int]$Default
    )

    if ($null -ne $Value) {
        return [Math]::Max(0, [int]$Value)
    }

    $envValue = [Environment]::GetEnvironmentVariable($EnvName)
    if (-not [string]::IsNullOrWhiteSpace($envValue)) {
        $parsed = 0
        if ([int]::TryParse($envValue, [ref]$parsed)) {
            return [Math]::Max(0, $parsed)
        }
    }

    return $Default
}

function Invoke-ProcessWithTimeout {
    param(
        [string]$FilePath,
        [string[]]$ArgumentList,
        [int]$TimeoutSeconds
    )

    $stdoutPath = [IO.Path]::GetTempFileName()
    $stderrPath = [IO.Path]::GetTempFileName()

    try {
        $process = Start-Process `
            -FilePath $FilePath `
            -ArgumentList $ArgumentList `
            -NoNewWindow `
            -PassThru `
            -RedirectStandardOutput $stdoutPath `
            -RedirectStandardError $stderrPath
        # PowerShell 5 can lose ExitCode after a fast process exits unless its
        # native handle is materialized while the process is still alive.
        $null = $process.Handle

        $timedOut = $false
        if ($TimeoutSeconds -gt 0) {
            try {
                Wait-Process -Id $process.Id -Timeout $TimeoutSeconds -ErrorAction Stop
            } catch {
                $timedOut = $true
                try {
                    Stop-Process -Id $process.Id -Force -ErrorAction SilentlyContinue
                } catch {
                    # Process may have exited between timeout and stop.
                }
            }
        } else {
            Wait-Process -Id $process.Id
        }

        if (-not $timedOut) {
            $process.WaitForExit()
        }
        $process.Refresh()
        $resolvedExitCode = if ($timedOut) { $null } elseif ($null -eq $process.ExitCode) { 1 } else { [int]$process.ExitCode }
        $output = @()
        if (Test-Path $stdoutPath) {
            $output += Get-Content -Path $stdoutPath -ErrorAction SilentlyContinue
        }
        if (Test-Path $stderrPath) {
            $output += Get-Content -Path $stderrPath -ErrorAction SilentlyContinue
        }

        return @{
            TimedOut = $timedOut
            ExitCode = $resolvedExitCode
            Output = $output
        }
    } finally {
        Remove-Item -LiteralPath $stdoutPath, $stderrPath -Force -ErrorAction SilentlyContinue
    }
}

$importWorkerCount = Get-IntSetting -Value $ImportWorkers -EnvName 'ABAH_IMPORT_WORKERS' -Default 3
$reportWorkerCount = Get-IntSetting -Value $ReportWorkers -EnvName 'ABAH_REPORT_WORKERS' -Default 3
$snapshotWorkerCount = Get-IntSetting -Value $SnapshotWorkers -EnvName 'ABAH_SNAPSHOT_WORKERS' -Default 3
$shadowWorkerCount = Get-IntSetting -Value $ShadowWorkers -EnvName 'ABAH_SHADOW_WORKERS' -Default 2
$queueWorkerMemory = Get-IntSetting -Value $WorkerMemory -EnvName 'ABAH_WORKER_MEMORY' -Default 512
$queueWorkerMaxJobs = Get-IntSetting -Value $WorkerMaxJobs -EnvName 'ABAH_WORKER_MAX_JOBS' -Default 25
$queueWorkerMaxTime = Get-IntSetting -Value $WorkerMaxTime -EnvName 'ABAH_WORKER_MAX_TIME' -Default 3600
$startupMigrateTimeout = Get-IntSetting -Value $null -EnvName 'ABAH_START_MIGRATE_TIMEOUT' -Default 240
$startupQueueRestartTimeout = Get-IntSetting -Value $null -EnvName 'ABAH_START_QUEUE_RESTART_TIMEOUT' -Default 60

Write-Host ("Worker policy: memory {0}MB, max-jobs {1}, max-time {2}s." -f $queueWorkerMemory, $queueWorkerMaxJobs, $queueWorkerMaxTime)

Write-Host 'Menerapkan runtime tuning database...'
try {
    & php artisan database:performance-tune --no-interaction
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Database tuning selesai dengan exit code $LASTEXITCODE."
    }
} catch {
    Write-Warning ("Database runtime tuning gagal: {0}" -f $_.Exception.Message)
}

# Ensure schema is current before spawning workers. Critical for snapshot dirty-period
# triggers (migration 2026_05_12_000002_create_dirty_marker_triggers.php) which let
# CRUD on hourly_dpk, ssa_simpanan, etc. propagate to dashboard_harian_snapshots
# automatically. If migrate fails we surface the error but continue so the user can
# still bring up workers and diagnose.
Write-Host ("Menjalankan php artisan migrate --force untuk memastikan trigger dirty-period dan schema terbaru terpasang (timeout {0}s)..." -f $startupMigrateTimeout)
try {
    $migrateResult = Invoke-ProcessWithTimeout `
        -FilePath 'php' `
        -ArgumentList @('artisan', 'migrate', '--force', '--no-interaction') `
        -TimeoutSeconds $startupMigrateTimeout

    if ($migrateResult.TimedOut) {
        Write-Warning ("php artisan migrate melewati timeout {0}s. Worker tetap dijalankan; cek database lock/migration manual." -f $startupMigrateTimeout)
    } elseif ($migrateResult.ExitCode -eq 0) {
        Write-Host 'Migrate selesai. Trigger snapshot dirty-period dipastikan terpasang.'
    } else {
        Write-Warning ("php artisan migrate keluar dengan kode {0}. Output:`n{1}" -f $migrateResult.ExitCode, ($migrateResult.Output -join "`n"))
        Write-Warning 'Worker tetap dijalankan, tetapi periksa migrasi sebelum mengandalkan auto-rebuild snapshot.'
    }
} catch {
    Write-Warning ("Tidak dapat menjalankan php artisan migrate: {0}" -f $_.Exception.Message)
}

Write-Host 'Menjalankan maintenance log sebelum worker dimulai...'
try {
    & php artisan logs:maintenance --no-interaction | Out-Null
    if ($LASTEXITCODE -ne 0) {
        Write-Warning "Log maintenance selesai dengan exit code $LASTEXITCODE."
    }
} catch {
    Write-Warning ("Log maintenance startup gagal: {0}" -f $_.Exception.Message)
}

if (-not $SkipQueueRestart) {
    Write-Host ("Mengirim sinyal queue:restart agar worker lama recycle aman setelah job aktif selesai (timeout {0}s)..." -f $startupQueueRestartTimeout)
    try {
        $restartResult = Invoke-ProcessWithTimeout `
            -FilePath 'php' `
            -ArgumentList @('artisan', 'queue:restart', '--no-interaction') `
            -TimeoutSeconds $startupQueueRestartTimeout

        if ($restartResult.TimedOut) {
            Write-Warning ("php artisan queue:restart melewati timeout {0}s. Worker baru tetap dijalankan." -f $startupQueueRestartTimeout)
        } elseif ($restartResult.ExitCode -eq 0) {
            Write-Host 'Sinyal queue:restart terkirim.'
        } else {
            Write-Warning ("php artisan queue:restart keluar dengan kode {0}. Output:`n{1}" -f $restartResult.ExitCode, ($restartResult.Output -join "`n"))
        }
    } catch {
        Write-Warning ("Tidak dapat menjalankan php artisan queue:restart: {0}" -f $_.Exception.Message)
    }
}

function Get-ProcessCommandCount {
    param([string]$Pattern)

    $count = 0

    try {
        $processes = Get-CimInstance Win32_Process -ErrorAction Stop

        foreach ($process in $processes) {
            $commandLine = $process.CommandLine
            if ($null -eq $commandLine) {
                $commandLine = ''
            }

            if ($commandLine -like "*$Pattern*") {
                $count++
            }
        }
    } catch {
        return 0
    }

    return $count
}

function Start-PersistentQueuePool {
    param(
        [string]$Name,
        [string]$Queues,
        [string]$LogPath,
        [string]$WorkerKey,
        [int]$DesiredCount = 1,
        [int]$Tries = 1
    )

    if ($DesiredCount -le 0) {
        Write-Host "$Name dilewati (jumlah worker 0)."
        return
    }

    $runningSlots = 0
    for ($slot = 1; $slot -le $DesiredCount; $slot++) {
        if ((Get-ProcessCommandCount -Pattern "queue-persistent.ps1*$WorkerKey-$slot") -gt 0) {
            $runningSlots++
        }
    }

    if ($runningSlots -ge $DesiredCount) {
        Write-Host "$Name sudah berjalan ($runningSlots/$DesiredCount worker)."
        return
    }

    for ($slot = 1; $slot -le $DesiredCount; $slot++) {
        if ((Get-ProcessCommandCount -Pattern "queue-persistent.ps1*$WorkerKey-$slot") -gt 0) {
            continue
        }

        $slotLogPath = $LogPath
        if ($DesiredCount -gt 1) {
            $directory = Split-Path -Parent $LogPath
            $baseName = [IO.Path]::GetFileNameWithoutExtension($LogPath)
            $extension = [IO.Path]::GetExtension($LogPath)
            $slotLogPath = Join-Path $directory ("{0}-{1}{2}" -f $baseName, $slot, $extension)
        }

        $slotWorkerName = "$WorkerKey-$slot"
        $scriptPath = Join-Path $projectRoot 'scripts\queue-persistent.ps1'
        $command = "cd /d `"$projectRoot`" && powershell -NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -Queues `"$Queues`" -Tries $Tries -Timeout 0 -Sleep 1 -Memory $queueWorkerMemory -MaxJobs $queueWorkerMaxJobs -MaxTimeSeconds $queueWorkerMaxTime -RestartDelaySeconds 3 -WorkerName `"$slotWorkerName`" >> `"$slotLogPath`" 2>&1"
        Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $command -WindowStyle Hidden | Out-Null
        Write-Host "$Name persistent worker $slot/$DesiredCount dijalankan. Log: $slotLogPath"
    }
}

function Start-PersistentScheduler {
    param(
        [string]$LogPath
    )

    $workerKey = 'abah-scheduler'
    $runningCount = Get-ProcessCommandCount -Pattern "schedule-persistent.ps1*$workerKey"
    if ($runningCount -gt 0) {
        Write-Host "Laravel scheduler sudah berjalan persistent."
        return
    }

    $scriptPath = Join-Path $projectRoot 'scripts\schedule-persistent.ps1'
    $command = "cd /d `"$projectRoot`" && powershell -NoProfile -ExecutionPolicy Bypass -File `"$scriptPath`" -RestartDelaySeconds 3 -WorkerName `"$workerKey`" >> `"$LogPath`" 2>&1"
    Start-Process -FilePath 'cmd.exe' -ArgumentList '/c', $command -WindowStyle Hidden | Out-Null
    Write-Host "Laravel scheduler persistent dijalankan. Log: $LogPath"
}

# --- Queue routing rationale ---
# imports-daily-loan is handled exclusively by import workers to prevent 12-worker
# competition for a single-worker job. Report workers focus on their own queues.
# Every high-cost workload has an explicit pool so one backlog cannot starve
# imports, remote refreshes, or priority snapshots.

Start-PersistentQueuePool `
    -Name 'Import queue worker' `
    -Queues 'imports-high' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-import-queue-worker.log') `
    -WorkerKey 'abah-import-worker' `
    -DesiredCount $importWorkerCount

Start-PersistentQueuePool `
    -Name 'Daily Loan import worker' `
    -Queues 'imports-daily-loan' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-daily-loan-worker.log') `
    -WorkerKey 'abah-daily-loan-worker' `
    -DesiredCount 1

Start-PersistentQueuePool `
    -Name 'Report queue worker' `
    -Queues 'default,reports-low' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-report-queue-worker.log') `
    -WorkerKey 'abah-report-worker' `
    -DesiredCount $reportWorkerCount

Start-PersistentQueuePool `
    -Name 'Remote source worker' `
    -Queues 'remote-sources' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-remote-source-worker.log') `
    -WorkerKey 'abah-remote-source-worker' `
    -DesiredCount 2 `
    -Tries 3

Start-PersistentQueuePool `
    -Name 'Priority snapshot worker' `
    -Queues 'snapshots-priority' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-priority-snapshot-worker.log') `
    -WorkerKey 'abah-priority-snapshot-worker' `
    -DesiredCount 1

Start-PersistentQueuePool `
    -Name 'Snapshot queue worker' `
    -Queues 'snapshots-parallel' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-snapshot-queue-worker.log') `
    -WorkerKey 'abah-snapshot-worker' `
    -DesiredCount $snapshotWorkerCount

Start-PersistentQueuePool `
    -Name 'Shadow backfill queue worker' `
    -Queues 'shadow-backfill' `
    -LogPath (Join-Path $projectRoot 'storage\logs\persistent-shadow-backfill-worker.log') `
    -WorkerKey 'abah-shadow-worker' `
    -DesiredCount $shadowWorkerCount

Start-PersistentScheduler -LogPath $scheduleLog

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
