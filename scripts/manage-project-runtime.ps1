[CmdletBinding()]
param(
    [ValidateSet('Status', 'StartScheduler', 'StopScheduler', 'RestartScheduler', 'RunScheduler', 'StartService', 'RunBackup')]
    [string]$Action = 'Status',
    [Parameter(Mandatory = $true)]
    [string]$ProjectDirectory,
    [Parameter(Mandatory = $true)]
    [string]$PhpBinary,
    [ValidateSet('', 'apache', 'mysql', 'tunnel')]
    [string]$ServiceKey = '',
    [string]$TaskName = 'Laravel-Scheduler-ProjectABAH'
)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'
$runtimeTaskName = $TaskName
$runtimeProject = $null
$runtimePhp = $null
$runtimeTaskService = $null
$runtimeMutex = $null
$runtimeMutexHeld = $false
$runtimeBackupTaskName = 'ProjectABAH-DailyDatabaseBackup'
# A tick normally takes seconds; the limit only stops a hung tick so the
# IgnoreNew policy can never block every following minute.
$runtimeTickTimeLimit = 'PT10M'
$runtimeServiceMap = [ordered]@{
    apache = @{ Name = 'Apache2.4'; Label = 'Apache' }
    mysql = @{ Name = 'mysql'; Label = 'MySQL' }
    tunnel = @{ Name = 'Cloudflared'; Label = 'Cloudflare Tunnel' }
}

function ConvertTo-RuntimePath {
    param([string]$Path)
    if ([string]::IsNullOrWhiteSpace($Path)) { return '' }
    return $Path.Trim().Trim('"').Replace('/', '\').TrimEnd('\').ToLowerInvariant()
}

function Get-RuntimeCommandTokens {
    param([string]$CommandLine)
    return @([regex]::Matches($CommandLine, '"([^"]*)"|(\S+)') | ForEach-Object {
        if ($_.Groups[1].Success) { $_.Groups[1].Value } else { $_.Groups[2].Value }
    })
}

function Test-RuntimeTaskOwned {
    param($Task)
    if ($null -eq $Task) { return $false }
    $actions = @($Task.Definition.Actions)
    if ($actions.Count -ne 1) { return $false }
    $taskAction = $actions[0]
    if ((ConvertTo-RuntimePath $taskAction.WorkingDirectory) -ne (ConvertTo-RuntimePath $runtimeProject)) { return $false }
    $executable = ConvertTo-RuntimePath $taskAction.Path
    $tokens = @(Get-RuntimeCommandTokens ([string]$taskAction.Arguments))
    if ($executable -eq (ConvertTo-RuntimePath $runtimePhp)) {
        if ($tokens.Count -lt 2 -or $tokens.Count -gt 3) { return $false }
        if ((ConvertTo-RuntimePath $tokens[0]) -ne (ConvertTo-RuntimePath (Join-Path $runtimeProject 'artisan'))) { return $false }
        if ($tokens[1] -notin @('system:scheduler-run', 'schedule:run')) { return $false }
        return $tokens.Count -eq 2 -or $tokens[2] -eq '--no-interaction'
    }
    $batchPath = ConvertTo-RuntimePath (Join-Path $runtimeProject 'php-scheduler.bat')
    if ($executable -eq $batchPath) { return [string]::IsNullOrWhiteSpace([string]$taskAction.Arguments) }
    if ([IO.Path]::GetFileName($executable) -ne 'cmd.exe') { return $false }
    $arguments = ([string]$taskAction.Arguments).Trim().Replace('/', '\').ToLowerInvariant()
    foreach ($prefix in @('\c ', '\d \c ', '\s \c ', '\d \s \c ')) {
        $singleQuoted = $prefix + '"' + $batchPath + '"'
        $doubleQuoted = $prefix + '""' + $batchPath + '""'
        if ($arguments -eq $singleQuoted -or $arguments -eq $doubleQuoted) { return $true }
    }
    return $false
}

function Get-RuntimeTask {
    param([string]$Name = $runtimeTaskName)
    if ($null -eq $script:runtimeTaskService) {
        $script:runtimeTaskService = New-Object -ComObject 'Schedule.Service'
        $script:runtimeTaskService.Connect()
    }
    try {
        return $script:runtimeTaskService.GetFolder('\').GetTask($Name)
    } catch {
        $taskException = $_.Exception
        while ($null -ne $taskException) {
            if ($taskException.HResult -eq -2147024894) { return $null }
            $taskException = $taskException.InnerException
        }
        # AccessDenied can mean an existing protected task. Never infer absence
        # from an authorized-only task enumeration or try to replace that task.
        throw
    }
}

function Get-RuntimeTaskStartupMode {
    param($Task)
    if ($Task.Definition.Principal.LogonType -eq 5) { return 'system' }
    if ($Task.Definition.Principal.LogonType -eq 3) { return 'login' }
    return $null
}

function Get-RuntimeTaskReadiness {
    param($Task)
    # Readiness after a reboot: a SYSTEM task with a boot trigger (or an
    # indefinitely repeating time trigger) runs before anyone logs in.
    $triggers = @($Task.Definition.Triggers)
    $hasBoot = @($triggers | Where-Object { $_.Type -eq 8 -and $_.Enabled }).Count -gt 0
    # Only an indefinite repetition (empty Duration) keeps firing after a reboot.
    $hasRepeating = @($triggers | Where-Object { $_.Type -eq 1 -and $_.Enabled -and -not [string]::IsNullOrWhiteSpace([string]$_.Repetition.Interval) -and [string]::IsNullOrWhiteSpace([string]$_.Repetition.Duration) }).Count -gt 0
    $limit = [string]$Task.Definition.Settings.ExecutionTimeLimit
    $unlimited = ([string]::IsNullOrWhiteSpace($limit) -or $limit -eq 'PT0S')
    return @{
        has_boot_trigger = $hasBoot
        has_repeating_trigger = $hasRepeating
        execution_time_limit = $limit
        unlimited_runtime = $unlimited
        # Weak = would not come back by itself after a reboot, or a hung tick could block every later minute.
        weak = ((Get-RuntimeTaskStartupMode $Task) -ne 'system') -or (-not ($hasBoot -or $hasRepeating)) -or $unlimited
    }
}

function Test-RuntimeCanRegisterSystemTask {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    if ($identity.IsSystem) { return $true }
    return ([Security.Principal.WindowsPrincipal]::new($identity)).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

function Test-BackupTaskOwned {
    param($Task)
    if ($null -eq $Task) { return $false }
    $actions = @($Task.Definition.Actions)
    if ($actions.Count -ne 1) { return $false }
    $launcher = ConvertTo-RuntimePath (Join-Path $runtimeProject 'database-backup-daily.bat')
    $executable = ConvertTo-RuntimePath $actions[0].Path
    if ($executable -eq $launcher) { return $true }
    if ([IO.Path]::GetFileName($executable) -ne 'cmd.exe') { return $false }
    $arguments = ([string]$actions[0].Arguments).Replace('/', '\').ToLowerInvariant()
    return $arguments.Contains($launcher)
}

function Get-RuntimeBackupStatus {
    $backup = @{ registered = $false; enabled = $false; state = 'unknown'; owned = $false; startup_mode = $null; last_run_at = $null; next_run_at = $null; last_result = $null; available = $true; message = '' }
    try {
        $task = Get-RuntimeTask -Name $runtimeBackupTaskName
        if ($null -eq $task) {
            $backup.state = 'unregistered'
            $backup.message = 'Task backup Windows belum terpasang; backup tetap dijalankan scheduler Laravel pukul 00:00.'
            return $backup
        }
        $backup.registered = $true
        $backup.owned = Test-BackupTaskOwned $task
        if (-not $backup.owned) {
            $backup.message = 'Task backup memakai launcher lain; tidak dikontrol dari panel.'
        }
        $backup.enabled = [bool]$task.Enabled
        $backup.state = switch ([int]$task.State) { 1 { 'disabled' } 2 { 'queued' } 3 { 'ready' } 4 { 'running' } default { 'unknown' } }
        $backup.last_result = [long]$task.LastTaskResult
        $backup.last_run_at = ConvertTo-RuntimeDate $task.LastRunTime
        $backup.next_run_at = ConvertTo-RuntimeDate $task.NextRunTime
        $backup.startup_mode = Get-RuntimeTaskStartupMode $task
    } catch {
        $backup.available = $false
        $backup.message = 'Task backup tidak dapat dibaca oleh akun server: ' + $_.Exception.Message
    }
    return $backup
}

function Get-SchedulerProcessObservation {
    param([object[]]$Processes)
    if (-not $PSBoundParameters.ContainsKey('Processes')) {
        try {
            $Processes = @(Get-CimInstance Win32_Process -Filter "Name = 'php.exe' OR Name = 'powershell.exe' OR Name = 'pwsh.exe' OR Name = 'cmd.exe'" -ErrorAction Stop)
        } catch {
            return @{ available = $false; scoped = @(); unknown_count = 0 }
        }
    }
    $processMap = @{}
    $provenWrapperIds = @{}
    $scoped = @()
    $unknownCount = 0
    $wrapperPath = ConvertTo-RuntimePath (Join-Path $runtimeProject 'scripts\schedule-persistent.ps1')
    $artisanPath = ConvertTo-RuntimePath (Join-Path $runtimeProject 'artisan')
    foreach ($observed in $Processes) { $processMap[[int]$observed.ProcessId] = $observed }
    foreach ($observed in $Processes) {
        if ([int]$observed.ProcessId -eq $PID -or $observed.Name -notin @('powershell.exe', 'pwsh.exe')) { continue }
        $tokens = @(Get-RuntimeCommandTokens ([string]$observed.CommandLine))
        $fileIndex = -1
        for ($tokenIndex = 1; $tokenIndex -lt $tokens.Count; $tokenIndex++) {
            if ($tokens[$tokenIndex] -match '^-f(?:i(?:l(?:e)?)?)?$') { $fileIndex = $tokenIndex; break }
        }
        if ($fileIndex -lt 0 -or $tokens.Count -le ($fileIndex + 1)) { continue }
        $scriptPath = ConvertTo-RuntimePath $tokens[$fileIndex + 1]
        if ($scriptPath -eq $wrapperPath) {
            $provenWrapperIds[[int]$observed.ProcessId] = $true
            $scoped += $observed
        } elseif ($scriptPath -match '(^|\\)schedule-persistent\.ps1$' -and -not [IO.Path]::IsPathRooted($scriptPath)) {
            $unknownCount++
        }
    }
    foreach ($observed in $Processes) {
        if ($observed.Name -ne 'php.exe' -or [int]$observed.ProcessId -eq $PID) { continue }
        $tokens = @(Get-RuntimeCommandTokens ([string]$observed.CommandLine))
        $workIndex = [Array]::IndexOf($tokens, 'schedule:work')
        if ($workIndex -lt 2) { continue }
        $artifact = ConvertTo-RuntimePath $tokens[$workIndex - 1]
        if ($artifact -eq $artisanPath) { $scoped += $observed; continue }
        if ([IO.Path]::IsPathRooted($artifact)) { continue }
        if ($artifact -ne 'artisan') { continue }
        $ancestorId = [int]$observed.ParentProcessId
        $childCreatedAt = $observed.CreationDate
        $proven = $false
        $visited = @{}
        for ($depth = 0; $depth -lt 12 -and $ancestorId -gt 0; $depth++) {
            if ($visited.ContainsKey($ancestorId)) { break }
            $visited[$ancestorId] = $true
            if (-not $processMap.ContainsKey($ancestorId)) { break }
            $ancestor = $processMap[$ancestorId]
            if ($null -eq $ancestor.CreationDate -or $null -eq $childCreatedAt -or $ancestor.CreationDate -gt $childCreatedAt) { break }
            if ($provenWrapperIds.ContainsKey($ancestorId)) { $proven = $true; break }
            $childCreatedAt = $ancestor.CreationDate
            $ancestorId = [int]$ancestor.ParentProcessId
        }
        if ($proven) { $scoped += $observed } else { $unknownCount++ }
    }
    return @{ available = $true; scoped = @($scoped); unknown_count = $unknownCount }
}

function ConvertTo-RuntimeDate {
    param($Value)
    if ($null -eq $Value) { return $null }
    try {
        $date = [datetime]$Value
        if ($date.Year -lt 2000) { return $null }
        return $date.ToUniversalTime().ToString('o')
    } catch { return $null }
}

function Get-RuntimeInfrastructure {
    $items = @()
    foreach ($key in $runtimeServiceMap.Keys) {
        $definition = $runtimeServiceMap[$key]
        $item = @{ key = $key; label = $definition.Label; state = 'unknown'; automatic = $null; startable = $false; message = '' }
        try {
            $service = Get-Service -Name $definition.Name -ErrorAction Stop
            $item.state = if ($service.Status -eq 'Running') { 'running' } elseif ($service.Status -eq 'Stopped') { 'stopped' } else { 'unknown' }
            $item.startable = $service.Status -eq 'Stopped'
            try {
                $serviceConfiguration = Get-CimInstance Win32_Service -Filter ("Name = '{0}'" -f $definition.Name) -ErrorAction Stop
                $item.automatic = $serviceConfiguration.StartMode -eq 'Auto'
            } catch { $item.message = 'Status startup otomatis tidak dapat dibaca oleh akun server.' }
            if ($item.state -eq 'unknown') { $item.message = 'Service sedang berpindah status; tunggu sebelum mencoba lagi.' }
        } catch { $item.message = 'Service Windows tidak ditemukan atau tidak dapat dibaca oleh akun server.' }
        $items += $item
    }
    return @($items)
}

function Get-ProjectRuntimeStatus {
    $observation = Get-SchedulerProcessObservation
    $bootTimestamp = $null
    try {
        $operatingSystem = Get-CimInstance Win32_OperatingSystem -ErrorAction Stop
        $bootTimestamp = ([DateTimeOffset]$operatingSystem.LastBootUpTime).ToUnixTimeSeconds()
    } catch { }
    $scheduler = @{ registered = $false; enabled = $false; state = 'unknown'; last_result = $null; last_run_at = $null; next_run_at = $null; startup_mode = $null; legacy_count = @($observation.scoped).Count; available = $true; conflict = $false; message = ''; has_boot_trigger = $false; has_repeating_trigger = $false; execution_time_limit = $null; needs_upgrade = $false }
    try {
        $task = Get-RuntimeTask
        if ($null -ne $task) {
            $scheduler.registered = $true
            if (-not (Test-RuntimeTaskOwned $task)) {
                $scheduler.available = $false
                $scheduler.conflict = $true
                $scheduler.message = 'Nama task scheduler sudah digunakan konfigurasi lain; task tersebut tidak diubah.'
            } else {
                $scheduler.enabled = [bool]$task.Enabled
                $scheduler.state = switch ([int]$task.State) { 1 { 'disabled' } 2 { 'queued' } 3 { 'ready' } 4 { 'running' } default { 'unknown' } }
                $scheduler.last_result = [long]$task.LastTaskResult
                $scheduler.last_run_at = ConvertTo-RuntimeDate $task.LastRunTime
                $scheduler.next_run_at = ConvertTo-RuntimeDate $task.NextRunTime
                $scheduler.startup_mode = Get-RuntimeTaskStartupMode $task
                $taskReadiness = Get-RuntimeTaskReadiness $task
                $scheduler.has_boot_trigger = $taskReadiness.has_boot_trigger
                $scheduler.has_repeating_trigger = $taskReadiness.has_repeating_trigger
                $scheduler.execution_time_limit = $taskReadiness.execution_time_limit
                $scheduler.needs_upgrade = $taskReadiness.weak
                if ($scheduler.enabled -and $scheduler.legacy_count -gt 0) {
                    $scheduler.conflict = $true
                    $scheduler.message = 'Task native dan scheduler persistent proyek sama-sama aktif. Hentikan scheduler sebelum memulai ulang.'
                }
            }
        } else { $scheduler.state = 'unregistered' }
    } catch {
        $scheduler.registered = $null
        $scheduler.available = $false
        $scheduler.message = 'Windows Task Scheduler tidak dapat diakses oleh akun server: ' + $_.Exception.Message
    }
    if ($observation.unknown_count -gt 0) {
        $scheduler.conflict = $true
        $scheduler.message = 'Ada scheduler PHP tanpa identitas direktori proyek yang pasti. Proses tersebut tidak dihentikan; start baru diblokir untuk menghindari duplikasi.'
    } elseif ($scheduler.legacy_count -gt 0 -and -not $scheduler.enabled -and $scheduler.available) {
        $scheduler.state = 'external'
        $scheduler.message = 'Scheduler persistent proyek sudah berjalan; task native baru tidak diperlukan.'
    }
    if (-not $observation.available) {
        $scheduler.available = $false
        $scheduler.message = 'Daftar proses Windows tidak dapat dibaca; start scheduler diblokir karena scheduler lain belum dapat dipastikan.'
    }
    return @{ scheduler = $scheduler; infrastructure = @(Get-RuntimeInfrastructure); backup = (Get-RuntimeBackupStatus); process_observation_available = [bool]$observation.available; boot_timestamp = $bootTimestamp }
}

function Test-RuntimePermissionDenied {
    param($Exception)
    $current = $Exception
    while ($null -ne $current) {
        if ($current -is [UnauthorizedAccessException] -or $current.HResult -in @(-2147024891, -2147023582)) { return $true }
        $current = $current.InnerException
    }
    return $false
}

function Register-ProjectScheduler {
    param([switch]$Interactive)
    $existing = Get-RuntimeTask
    if ($null -ne $existing -and -not (Test-RuntimeTaskOwned $existing)) { throw 'Task scheduler lain memakai nama yang sama; pendaftaran dibatalkan.' }
    $definition = $script:runtimeTaskService.NewTask(0)
    $definition.RegistrationInfo.Description = 'Scheduler Project ABAH; satu tick per menit, mulai kembali setelah server aktif.'
    $definition.Settings.Enabled = $true
    $definition.Settings.Hidden = $true
    $definition.Settings.AllowDemandStart = $true
    $definition.Settings.StartWhenAvailable = $true
    $definition.Settings.DisallowStartIfOnBatteries = $false
    $definition.Settings.StopIfGoingOnBatteries = $false
    $definition.Settings.MultipleInstances = 2
    $definition.Settings.ExecutionTimeLimit = $runtimeTickTimeLimit
    $trigger = $definition.Triggers.Create(1)
    $trigger.StartBoundary = (Get-Date).AddSeconds(5).ToString('yyyy-MM-ddTHH:mm:ss')
    $trigger.Repetition.Interval = 'PT1M'
    $trigger.Repetition.StopAtDurationEnd = $false
    $trigger.Enabled = $true
    if ($Interactive -and [Security.Principal.WindowsIdentity]::GetCurrent().IsSystem) { throw 'Task SYSTEM ditolak dan akun server tidak memiliki sesi login interaktif. Daftarkan scheduler dengan izin Windows yang sesuai.' }
    $userId = if ($Interactive) { [Security.Principal.WindowsIdentity]::GetCurrent().Name } else { 'SYSTEM' }
    $logonType = if ($Interactive) { 3 } else { 5 }
    $definition.Principal.UserId = $userId
    $definition.Principal.LogonType = $logonType
    $definition.Principal.RunLevel = if ($Interactive) { 0 } else { 1 }
    $startup = $definition.Triggers.Create($(if ($Interactive) { 9 } else { 8 }))
    if ($Interactive) { $startup.UserId = $userId }
    $startup.Enabled = $true
    $taskAction = $definition.Actions.Create(0)
    $taskAction.Path = $runtimePhp
    $taskAction.Arguments = '"' + (Join-Path $runtimeProject 'artisan') + '" system:scheduler-run --no-interaction'
    $taskAction.WorkingDirectory = $runtimeProject
    $registrationFlags = if ($null -eq $existing) { 2 } else { 6 }
    $null = $script:runtimeTaskService.GetFolder('\').RegisterTaskDefinition($runtimeTaskName, $definition, $registrationFlags, $userId, $null, $logonType, $null)
}

function Start-ProjectScheduler {
    $status = Get-ProjectRuntimeStatus
    if (-not $status.scheduler.available -or $status.scheduler.conflict) { throw $status.scheduler.message }
    if ($status.scheduler.legacy_count -gt 0) { return 'Scheduler persistent proyek sudah aktif; tidak ada scheduler kedua yang dijalankan.' }
    $existingTask = Get-RuntimeTask
    if ($null -ne $existingTask -and [bool]$existingTask.Enabled) {
        # An enabled task may still be a weak setup (login-only, no boot trigger,
        # or no runtime limit). Upgrade it only where a SYSTEM task can be saved;
        # re-registering never stops a tick that is already running.
        $weak = (Get-RuntimeTaskReadiness $existingTask).weak
        if ($weak -and $runtimeTaskName -eq 'Laravel-Scheduler-ProjectABAH' -and (Test-RuntimeCanRegisterSystemTask)) {
            Register-ProjectScheduler
            $existingTask = Get-RuntimeTask
            if ([int]$existingTask.State -ne 4) { $null = $existingTask.Run($null) }
            return 'Task scheduler diperbarui menjadi task SYSTEM yang otomatis menyala saat Windows boot.'
        }
        if ([int]$existingTask.State -ne 4) {
            $null = $existingTask.Run($null)
            return 'Scheduler proyek sudah aktif; satu tick dijalankan sekarang untuk memastikan heartbeat.'
        }
        return 'Scheduler proyek sudah aktif; tick sedang berjalan.'
    }
    if ($runtimeTaskName -ne 'Laravel-Scheduler-ProjectABAH') {
        Register-ProjectScheduler -Interactive
    } else {
        try { Register-ProjectScheduler } catch {
            if (-not (Test-RuntimePermissionDenied $_.Exception)) { throw }
            Register-ProjectScheduler -Interactive
        }
    }
    $task = Get-RuntimeTask
    if ([int]$task.State -ne 4) { $null = $task.Run($null) }
    if ($task.Definition.Principal.LogonType -eq 3) { return 'Scheduler aktif dalam mode login Windows; berjalan setelah akun Windows ini login.' }
    return 'Scheduler aktif dan akan dimulai otomatis setelah Windows menyala.'
}

function Stop-ProjectScheduler {
    $task = Get-RuntimeTask
    if ($null -ne $task) {
        if (-not (Test-RuntimeTaskOwned $task)) { throw 'Task scheduler bukan milik direktori proyek ini; tidak dihentikan.' }
        # Disabling future triggers does not terminate an active tick or its jobs.
        $task.Enabled = $false
    }
    $observation = Get-SchedulerProcessObservation
    if (-not $observation.available) { throw 'Task dinonaktifkan, tetapi daftar proses tidak dapat diverifikasi. Scheduler persistent tidak dihentikan.' }
    # Stop restart wrappers before their schedule:work children. Never kill a tree.
    $ordered = @($observation.scoped | Sort-Object @{ Expression = { if ($_.Name -eq 'php.exe') { 1 } else { 0 } } })
    foreach ($observed in $ordered) {
        $current = Get-CimInstance Win32_Process -Filter ("ProcessId = {0}" -f [int]$observed.ProcessId) -ErrorAction Stop
        if ($null -eq $current) { continue }
        if ([string]$current.CommandLine -ne [string]$observed.CommandLine -or $current.CreationDate -ne $observed.CreationDate) { throw 'Identitas PID scheduler berubah; proses pengganti tidak dihentikan.' }
        Stop-Process -Id ([int]$observed.ProcessId) -ErrorAction Stop
    }
    if ($observation.unknown_count -gt 0) { throw 'Scheduler proyek dihentikan, tetapi proses scheduler tanpa identitas proyek tetap dibiarkan berjalan.' }
    return 'Pemicu scheduler dihentikan. Tick, import, dan job yang sudah berjalan tetap dilanjutkan.'
}

function Start-ProjectInfrastructureService {
    if (-not $runtimeServiceMap.Contains($ServiceKey)) { throw 'Pilih service Apache, MySQL, atau Cloudflare Tunnel.' }
    $definition = $runtimeServiceMap[$ServiceKey]
    $service = Get-Service -Name $definition.Name -ErrorAction Stop
    $automaticNote = ''
    try {
        $configuration = Get-CimInstance Win32_Service -Filter ("Name = '{0}'" -f $definition.Name) -ErrorAction Stop
        if ($configuration.StartMode -ne 'Auto') {
            # Automatic startup is what brings the service back after a reboot.
            Set-Service -Name $definition.Name -StartupType Automatic -ErrorAction Stop
            $automaticNote = ' Startup diubah menjadi otomatis.'
        }
    } catch { $automaticNote = ' Startup otomatis tidak dapat diubah oleh akun server.' }
    if ($service.Status -eq 'Running') { return $definition.Label + ' sudah berjalan.' + $automaticNote }
    if ($service.Status -ne 'Stopped') { throw ($definition.Label + ' sedang berpindah status. Tunggu sebelum mencoba lagi.') }
    Start-Service -Name $definition.Name -ErrorAction Stop
    $service.WaitForStatus([System.ServiceProcess.ServiceControllerStatus]::Running, [timespan]::FromSeconds(10))
    return $definition.Label + ' berhasil dimulai.' + $automaticNote
}

function Start-ProjectBackup {
    $task = Get-RuntimeTask -Name $runtimeBackupTaskName
    if ($null -eq $task) { throw 'Task backup Windows belum terpasang. Jalankan scripts/install-daily-database-backup-task.ps1 sebagai Administrator.' }
    if (-not (Test-BackupTaskOwned $task)) { throw 'Task backup memakai launcher lain; tidak dijalankan dari panel.' }
    if ([int]$task.State -eq 4) { return 'Backup database sedang berjalan; tidak dijalankan ganda.' }
    $null = $task.Run($null)
    return 'Backup database dijalankan melalui Windows Task Scheduler. Backup hari ini yang sudah terverifikasi dipakai ulang.'
}

try {
    $runtimeProject = (Resolve-Path -LiteralPath $ProjectDirectory -ErrorAction Stop).Path.TrimEnd('\', '/')
    $runtimePhp = (Resolve-Path -LiteralPath $PhpBinary -ErrorAction Stop).Path
    if (-not (Test-Path -LiteralPath (Join-Path $runtimeProject 'artisan') -PathType Leaf)) { throw 'Direktori proyek tidak memiliki artisan.' }
    if ([IO.Path]::GetFileName($runtimePhp) -ne 'php.exe') { throw 'Executable harus PHP CLI Windows (php.exe).' }
    if ($runtimeTaskName -ne 'Laravel-Scheduler-ProjectABAH') {
        if ($runtimeTaskName -cnotmatch '^ProjectABAH-SchedulerTest-[a-f0-9]{12}$') { throw 'Nama task pengujian tidak valid.' }
        $runtimeRepository = (Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..') -ErrorAction Stop).Path
        $runtimeTestingRoot = ConvertTo-RuntimePath ([IO.Path]::GetFullPath((Join-Path $runtimeRepository 'storage\framework\testing')))
        if (-not (ConvertTo-RuntimePath $runtimeProject).StartsWith($runtimeTestingRoot + '\')) { throw 'Task pengujian hanya boleh menggunakan direktori fixture di storage/framework/testing.' }
        $fixtureAncestor = Get-Item -LiteralPath $runtimeProject -Force
        while ((ConvertTo-RuntimePath $fixtureAncestor.FullName) -ne $runtimeTestingRoot) {
            if (($fixtureAncestor.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Direktori fixture tidak boleh memakai symlink atau junction.' }
            $fixtureAncestor = $fixtureAncestor.Parent
            if ($null -eq $fixtureAncestor) { throw 'Direktori fixture berada di luar root pengujian.' }
        }
        if (($fixtureAncestor.Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Root pengujian tidak boleh memakai symlink atau junction.' }
        if (((Get-Item -LiteralPath (Join-Path $runtimeProject 'artisan') -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0) { throw 'Artisan fixture tidak boleh memakai symlink.' }
        if ($Action -in @('StartService', 'RunBackup')) { throw 'Task pengujian tidak dapat mengontrol service infrastruktur atau backup.' }
    }
    if ($Action -ne 'Status') {
        $hasher = [Security.Cryptography.SHA1]::Create()
        try { $projectHash = ([BitConverter]::ToString($hasher.ComputeHash([Text.Encoding]::UTF8.GetBytes((ConvertTo-RuntimePath $runtimeProject))))).Replace('-', '').ToLowerInvariant() } finally { $hasher.Dispose() }
        $runtimeMutex = New-Object Threading.Mutex($false, ('Global\ProjectABAH-Runtime-' + $projectHash))
        try { $runtimeMutexHeld = $runtimeMutex.WaitOne(0) } catch [Threading.AbandonedMutexException] { $runtimeMutexHeld = $true }
        if (-not $runtimeMutexHeld) { throw 'Kontrol runtime proyek sedang diproses. Tunggu lalu coba lagi.' }
    }
    $actionMessage = switch ($Action) {
        'StartScheduler' { Start-ProjectScheduler }
        'StopScheduler' { Stop-ProjectScheduler }
        'RestartScheduler' { $null = Stop-ProjectScheduler; Start-ProjectScheduler }
        'RunScheduler' {
            $runStatus = Get-ProjectRuntimeStatus
            if (-not $runStatus.scheduler.available -or $runStatus.scheduler.conflict) { throw $runStatus.scheduler.message }
            $task = Get-RuntimeTask
            if ($null -eq $task -or -not (Test-RuntimeTaskOwned $task)) { throw 'Task scheduler proyek belum terdaftar.' }
            if (-not $task.Enabled) { throw 'Aktifkan scheduler sebelum menjalankan tick.' }
            if ([int]$task.State -eq 4) { 'Tick scheduler sedang berjalan; tidak dijalankan ganda.' } else { $null = $task.Run($null); 'Tick scheduler dijadwalkan melalui Windows Task Scheduler.' }
        }
        'StartService' { Start-ProjectInfrastructureService }
        'RunBackup' { Start-ProjectBackup }
    }
    $result = Get-ProjectRuntimeStatus
    if ($Action -ne 'Status') { $result.success = $true; $result.message = [string]$actionMessage }
    [Console]::Out.WriteLine(($result | ConvertTo-Json -Depth 7 -Compress))
    $runtimeExitCode = 0
} catch {
    $runtimeFailure = $_.Exception.Message
    try { $result = Get-ProjectRuntimeStatus } catch { $result = @{ scheduler = @{ available = $false; conflict = $false; message = $runtimeFailure }; infrastructure = @(); backup = $null; process_observation_available = $false; boot_timestamp = $null } }
    $result.success = $false
    $result.message = $runtimeFailure
    [Console]::Out.WriteLine(($result | ConvertTo-Json -Depth 7 -Compress))
    $runtimeExitCode = 1
} finally {
    if ($runtimeMutexHeld) { $runtimeMutex.ReleaseMutex() }
    if ($null -ne $runtimeMutex) { $runtimeMutex.Dispose() }
}
exit $runtimeExitCode
