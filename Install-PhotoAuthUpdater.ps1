param(
    [string]$ProjectDirectory,
    [string]$EnvironmentFile,
    [string]$ComposerExecutable,
    [ValidateRange(1, 65535)][int]$AgentPort = 19100,
    [ValidateSet('iis', 'service')][string]$RestartKind,
    [string]$RestartTarget,
    [string]$HealthUrl
)
#Requires -RunAsAdministrator
$ErrorActionPreference = 'Stop'
$appDir = (Resolve-Path $(if ($ProjectDirectory) { $ProjectDirectory } else { Read-Host 'Полный путь к папке PhotoAuthApp с artisan и .git' })).Path
if (-not (Test-Path (Join-Path $appDir 'artisan')) -or -not (Test-Path (Join-Path $appDir '.git'))) {
    throw 'Указана не папка Git-проекта PhotoAuthApp.'
}
$envFile = if ($EnvironmentFile) { $EnvironmentFile } else { Read-Host 'Файл окружения проекта [.env]' }
if (-not $envFile) { $envFile = '.env' }
if (-not [IO.Path]::IsPathRooted($envFile)) { $envFile = Join-Path $appDir $envFile }
if (-not (Test-Path $envFile)) { throw "Не найден файл окружения: $envFile" }

$restartKind = if ($RestartKind) { $RestartKind } else { Read-Host 'Перезапуск: IIS или SERVICE (Apache/XAMPP как служба)' }
$restartKind = $restartKind.Trim().ToLowerInvariant()
if ($restartKind -notin @('iis', 'service')) { throw 'Укажите IIS или SERVICE.' }
$restartTarget = if ($RestartTarget) { $RestartTarget } else { Read-Host $(if ($restartKind -eq 'iis') { 'Имя пула IIS' } else { 'Имя службы Windows Apache/XAMPP' }) }
if (-not $restartTarget -or $restartTarget -match '["`$;|&<>]') { throw 'Недопустимое имя службы или пула.' }
$healthUrl = if ($HealthUrl) { $HealthUrl } else { Read-Host 'Адрес проверки PhotoAuthApp [http://127.0.0.1/login]' }
if (-not $healthUrl) { $healthUrl = 'http://127.0.0.1/login' }
$python = (Get-Command python.exe -ErrorAction Stop).Source
$php = (Get-Command php.exe -ErrorAction Stop).Source
$composer = if ($ComposerExecutable) { (Resolve-Path -LiteralPath $ComposerExecutable).Path } else { (Get-Command composer.bat -ErrorAction SilentlyContinue).Source }
if (-not $composer) { $composer = (Get-Command composer.exe -ErrorAction Stop).Source }
$git = (Get-Command git.exe -ErrorAction Stop).Source
$pythonVersion = (& $python --version 2>&1 | Out-String).Trim()
if ($pythonVersion -notmatch 'Python\s+3\.(1[0-9]|[1-9][0-9])') { throw 'Для агента требуется Python 3.10 или новее.' }

$tokenLine = Get-Content -LiteralPath $envFile | Where-Object { $_ -match '^UPDATE_AGENT_TOKEN=' } | Select-Object -Last 1
$token = if ($tokenLine) { ($tokenLine -split '=', 2)[1].Trim().Trim('"').Trim("'") } else { '' }
if (-not $token) {
    $bytes = New-Object byte[] 32
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    $rng.GetBytes($bytes)
    $rng.Dispose()
    $token = [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
    $envLines = @(Get-Content -LiteralPath $envFile)
    if ($envLines | Where-Object { $_ -match '^UPDATE_AGENT_TOKEN=' }) {
        $envLines = @($envLines | ForEach-Object { if ($_ -match '^UPDATE_AGENT_TOKEN=') { "UPDATE_AGENT_TOKEN=$token" } else { $_ } })
        Set-Content -LiteralPath $envFile -Value $envLines -Encoding UTF8
    } else {
        Add-Content -LiteralPath $envFile -Value "UPDATE_AGENT_TOKEN=$token"
    }
} elseif ($token.Length -lt 32 -or $token -notmatch '^[A-Za-z0-9_-]+$') {
    throw 'UPDATE_AGENT_TOKEN должен содержать не менее 32 букв, цифр, дефисов или подчёркиваний.'
}
$phpVersion = (& $php -r 'echo PHP_VERSION;').Trim()
if ($LASTEXITCODE -ne 0 -or -not $phpVersion) { throw 'PHP CLI недоступен для выбранной учётной записи.' }
if ($restartKind -eq 'service') {
    if (-not (Get-Service -Name $restartTarget -ErrorAction SilentlyContinue)) { throw "Служба Windows $restartTarget не найдена." }
} else {
    $appcmd = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
    if (-not (Test-Path $appcmd)) { throw 'Для типа IIS не найден appcmd.exe.' }
    & $appcmd list apppool "/name:$restartTarget" | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Пул IIS $restartTarget не найден." }
}

$configDir = Join-Path $env:ProgramData 'PhotoAuthAppUpdater'
New-Item -ItemType Directory -Force -Path $configDir | Out-Null
$configPath = Join-Path $configDir 'settings.json'
$stateDir = Join-Path $configDir 'state'
New-Item -ItemType Directory -Force -Path $stateDir | Out-Null
$settings = @{
    PHOTOAUTHAPP_DIR = $appDir
    UPDATE_STATE_DIR = $stateDir
    UPDATE_AGENT_TOKEN = $token
    UPDATE_MODE = 'host'
    UPDATE_AGENT_HOST = '127.0.0.1'
    UPDATE_AGENT_PORT = "$AgentPort"
    UPDATE_RESTART_KIND = $restartKind
    UPDATE_RESTART_TARGET = $restartTarget
    UPDATE_HEALTH_URL = $healthUrl
    PYTHON_EXE = $python
    PHP_EXE = $php
    COMPOSER_EXE = $composer
    GIT_EXE = $git
}
$settings | ConvertTo-Json | Set-Content -LiteralPath $configPath -Encoding UTF8
& icacls.exe $configDir /inheritance:r /grant:r 'SYSTEM:(OI)(CI)F' 'Administrators:(OI)(CI)F' | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Не удалось ограничить доступ к файлу настроек агента.' }

$wrapper = Join-Path $appDir 'update-agent\Start-HostAgent.ps1'
$powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
$action = New-ScheduledTaskAction -Execute $powershell -Argument "-NoProfile -WindowStyle Hidden -ExecutionPolicy Bypass -File `"$wrapper`" -ConfigPath `"$configPath`""
$trigger = New-ScheduledTaskTrigger -AtStartup
$principal = New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount -RunLevel Highest
$settingsTask = New-ScheduledTaskSettingsSet -RestartCount 3 -RestartInterval (New-TimeSpan -Minutes 1) -ExecutionTimeLimit ([TimeSpan]::Zero) -MultipleInstances IgnoreNew
Register-ScheduledTask -TaskName 'PhotoAuthAppUpdater' -Action $action -Trigger $trigger -Principal $principal -Settings $settingsTask -Force | Out-Null
Start-ScheduledTask -TaskName 'PhotoAuthAppUpdater'
& $php (Join-Path $appDir 'artisan') config:clear
if ($LASTEXITCODE -ne 0) { throw 'Не удалось очистить кэш конфигурации Laravel.' }
if ($restartKind -eq 'iis') {
    & $appcmd recycle apppool "/apppool.name:$restartTarget"
    if ($LASTEXITCODE -ne 0) { throw "Не удалось перезапустить пул IIS $restartTarget." }
} else {
    Restart-Service -Name $restartTarget -Force
}
Write-Host "Агент ОС установлен и запущен; адрес доступен только на этом компьютере: 127.0.0.1:$AgentPort."
