$ErrorActionPreference = 'Stop'
$repairIdentity = [Security.Principal.WindowsIdentity]::GetCurrent()
$repairPrincipal = New-Object Security.Principal.WindowsPrincipal($repairIdentity)
if (-not $repairPrincipal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    throw 'Jalankan script ini dari PowerShell Run as Administrator.'
}
$repairRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$repairNormalized = $repairRoot.Replace('\', '/').ToLowerInvariant()
$repairHasher = [Security.Cryptography.SHA1]::Create()
try {
    $repairHash = ([BitConverter]::ToString($repairHasher.ComputeHash([Text.Encoding]::UTF8.GetBytes($repairNormalized)))).Replace('-', '').ToLowerInvariant().Substring(0, 12)
} finally {
    $repairHasher.Dispose()
}
$repairTaskName = 'ProjectABAH-QueueSupervisor-' + $repairHash
$repairWrapper = Join-Path $repairRoot ('storage\framework\queue-launchers\queue-monitor-' + $repairHash + '.cmd.vbs')
if (-not (Test-Path -LiteralPath $repairWrapper -PathType Leaf)) {
    throw "Hidden launcher tidak ditemukan: $repairWrapper"
}
$repairTask = Get-ScheduledTask -TaskName $repairTaskName
$repairAction = New-ScheduledTaskAction -Execute (Join-Path $env:WINDIR 'System32\wscript.exe') -Argument ('//B //NoLogo "' + $repairWrapper + '"') -WorkingDirectory $repairRoot
$repairTask.Settings.Hidden = $true
$repairTask.Settings.MultipleInstances = 2 # IgnoreNew; never stack monitor instances.
Set-ScheduledTask -TaskName $repairTaskName -Action $repairAction -Settings $repairTask.Settings | Out-Null
Get-ScheduledTask -TaskName $repairTaskName | Select-Object TaskName, State, @{Name='Execute';Expression={$_.Actions.Execute}}, @{Name='Arguments';Expression={$_.Actions.Arguments}}
Write-Output 'Action supervisor diubah menjadi hidden. Tidak ada worker aktif yang dihentikan.'
