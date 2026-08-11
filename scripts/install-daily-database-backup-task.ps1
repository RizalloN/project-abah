[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'

$taskName = 'ProjectABAH-DailyDatabaseBackup'
$projectDirectory = Split-Path -Parent $PSScriptRoot
$launcherPath = Join-Path $projectDirectory 'database-backup-daily.bat'

function Test-IsAdministrator {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)

    return $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)
}

if (-not (Test-Path -LiteralPath $launcherPath -PathType Leaf)) {
    throw "Launcher backup tidak ditemukan: $launcherPath"
}

$isAdministrator = Test-IsAdministrator
if (-not $isAdministrator) {
    # Standard users can own an interactive task without storing a Windows
    # password. StartWhenAvailable makes a missed midnight run catch up after
    # the next login. Running this installer as Administrator upgrades it to
    # the preferred SYSTEM task below, which also works while logged off.
    $taskCommand = '"' + $launcherPath + '"'
    & schtasks.exe `
        /Create `
        /TN $taskName `
        /TR $taskCommand `
        /SC DAILY `
        /ST '00:00' `
        /RL LIMITED `
        /IT `
        /F | Out-Host

    if ($LASTEXITCODE -ne 0) {
        throw 'Task Scheduler menolak pendaftaran task backup untuk user saat ini.'
    }

    $scheduleService = New-Object -ComObject 'Schedule.Service'
    $scheduleService.Connect()
    $scheduleFolder = $scheduleService.GetFolder('\')
    $scheduledTask = $scheduleFolder.GetTask($taskName)
    $definition = $scheduledTask.Definition
    $definition.RegistrationInfo.Description = 'Backup database Project ABAH setiap hari pukul 00:00; simpan satu backup terbaru yang terverifikasi.'
    $definition.Settings.StartWhenAvailable = $true
    $definition.Settings.DisallowStartIfOnBatteries = $false
    $definition.Settings.StopIfGoingOnBatteries = $false
    $definition.Settings.WakeToRun = $true
    $definition.Settings.MultipleInstances = 2
    $definition.Settings.ExecutionTimeLimit = 'PT20H'
    $null = $scheduleFolder.RegisterTaskDefinition(
        $taskName,
        $definition,
        6,
        $null,
        $null,
        3,
        $null
    )

    $savedTask = $scheduleFolder.GetTask($taskName)
    [pscustomobject]@{
        TaskName = $savedTask.Name
        State = $savedTask.State
        NextRunTime = $savedTask.NextRunTime
        UserId = $savedTask.Definition.Principal.UserId
        LogonType = 'InteractiveToken'
        Launcher = $launcherPath
        Note = 'Jalankan installer sebagai Administrator untuk upgrade ke SYSTEM.'
    }

    return
}

$trigger = New-ScheduledTaskTrigger -Daily -At '00:00'
$action = New-ScheduledTaskAction `
    -Execute 'cmd.exe' `
    -Argument "/d /c `"`"$launcherPath`"`"" `
    -WorkingDirectory $projectDirectory
$settings = New-ScheduledTaskSettingsSet `
    -AllowStartIfOnBatteries `
    -DontStopIfGoingOnBatteries `
    -StartWhenAvailable `
    -WakeToRun `
    -MultipleInstances IgnoreNew `
    -ExecutionTimeLimit (New-TimeSpan -Hours 20)
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -RunLevel Highest

Register-ScheduledTask `
    -TaskName $taskName `
    -Description 'Backup database Project ABAH setiap hari pukul 00:00; simpan satu backup terbaru yang terverifikasi.' `
    -Trigger $trigger `
    -Action $action `
    -Settings $settings `
    -Principal $principal `
    -Force | Out-Null

$task = Get-ScheduledTask -TaskName $taskName -ErrorAction Stop
$info = Get-ScheduledTaskInfo -TaskName $taskName -ErrorAction Stop

[pscustomobject]@{
    TaskName = $task.TaskName
    State = $task.State.ToString()
    NextRunTime = $info.NextRunTime
    UserId = $task.Principal.UserId
    LogonType = $task.Principal.LogonType.ToString()
    Launcher = $launcherPath
}
