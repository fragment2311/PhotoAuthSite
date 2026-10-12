param([ValidateSet('auto', 'docker', 'host')][string]$Mode = 'auto')
$ErrorActionPreference = 'Stop'
$appDir = Split-Path -Parent $PSCommandPath

function Assert-DockerReady {
    $docker = Get-Command docker.exe -ErrorAction SilentlyContinue
    if (-not $docker) { throw 'Docker не найден. Установите и запустите Docker Desktop либо выберите установку без Docker.' }
    & $docker.Source compose version *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Docker Compose недоступен. Проверьте установку Docker Desktop.' }
    & $docker.Source info --format '{{.ServerVersion}}' *> $null
    if ($LASTEXITCODE -ne 0) { throw 'Docker установлен, но его движок не отвечает. Запустите Docker Desktop, дождитесь запуска и повторите установку.' }
    return $docker.Source
}

if ($Mode -eq 'auto') {
    $docker = Get-Command docker.exe -ErrorAction SilentlyContinue
    if ($docker) {
        & $docker.Source compose version *> $null
        if ($LASTEXITCODE -eq 0) {
            $choice = Read-Host 'Обнаружен Docker Compose. Выберите режим: D (Docker) или W (Windows без Docker) [D]'
            $Mode = if ($choice -match '^[Ww]$') { 'host' } else { 'docker' }
        } else { Write-Host 'Docker Compose недоступен. Выбран режим Windows без Docker.'; $Mode = 'host' }
    } else { Write-Host 'Docker не найден. Выбран режим Windows без Docker.'; $Mode = 'host' }
}
if ($Mode -eq 'docker') { $null = Assert-DockerReady }

if (-not (Test-Path (Join-Path $appDir 'artisan')) -or -not (Test-Path (Join-Path $appDir '.git'))) {
    $defaultTarget = Join-Path $appDir 'PhotoAuthApp'
    $enteredTarget = Read-Host "Куда установить PhotoAuthApp? [$defaultTarget]"
    $target = [IO.Path]::GetFullPath($(if ($enteredTarget) { $enteredTarget } else { $defaultTarget }))
    if (Test-Path -LiteralPath (Join-Path $target 'artisan')) {
        if (-not (Test-Path -LiteralPath (Join-Path $target '.git'))) {
            throw "В $target уже есть файлы проекта без Git. Выберите другую папку для установки с обновлениями."
        }
    } else {
        if ((Test-Path -LiteralPath $target) -and @(Get-ChildItem -LiteralPath $target -Force).Count -gt 0) {
            throw "Папка $target не пуста. Выберите пустую папку для новой установки."
        }
        $git = Get-Command git.exe -ErrorAction SilentlyContinue
        if (-not $git) {
            $answer = Read-Host 'Для скачивания проекта нужен Git. Установить его через winget? Y/N [N]'
            if ($answer -notmatch '^[Yy]$') { throw 'Git необходим для установки и последующих обновлений PhotoAuthApp.' }
            $winget = Get-Command winget.exe -ErrorAction SilentlyContinue
            if (-not $winget) { throw 'WinGet недоступен. Не удалось установить Git автоматически.' }
            & $winget.Source install --exact --id Git.Git --source winget --accept-source-agreements --accept-package-agreements --disable-interactivity
            if ($LASTEXITCODE -ne 0) { throw 'Не удалось установить Git через winget.' }
            $env:PATH = [Environment]::GetEnvironmentVariable('PATH', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('PATH', 'User')
            $git = Get-Command git.exe -ErrorAction SilentlyContinue
            if (-not $git) { throw 'Git установлен, но пока недоступен. Повторите запуск установщика после входа в Windows.' }
        }
        Write-Host "Скачиваю PhotoAuthApp в $target..."
        & $git.Source clone --branch main --single-branch 'https://github.com/fragment2311/PhotoAuthSite.git' $target
        if ($LASTEXITCODE -ne 0) { throw 'Не удалось скачать PhotoAuthApp с GitHub. Проверьте подключение и доступ к репозиторию.' }
    }
    $installedScript = Join-Path $target 'Install-PhotoAuthApp.ps1'
    if (-not (Test-Path -LiteralPath $installedScript)) {
        throw "В скачанном проекте пока нет нового установщика. Обновите PhotoAuthApp на GitHub и повторите запуск. Каталог сохранён: $target"
    }
    $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
    & $powershell -NoProfile -ExecutionPolicy Bypass -File $installedScript -Mode $Mode
    exit $LASTEXITCODE
}

function New-RandomHex {
    $bytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    return [BitConverter]::ToString($bytes).Replace('-', '').ToLowerInvariant()
}

function Get-EnvValue([string]$Path, [string]$Key) {
    $line = @(Get-Content -LiteralPath $Path | Where-Object { $_ -match ('^' + [regex]::Escape($Key) + '=') } | Select-Object -Last 1)
    if ($line.Count -eq 0) { return '' }
    return ($line[0] -split '=', 2)[1].Trim().Trim('"').Trim("'")
}

function Set-EnvValue([string]$Path, [string]$Key, [string]$Value) {
    $lines = [Collections.Generic.List[string]]::new()
    $found = $false
    foreach ($line in [IO.File]::ReadAllLines($Path)) {
        if ($line -match ('^' + [regex]::Escape($Key) + '=')) {
            if (-not $found) { $lines.Add("$Key=$Value"); $found = $true }
        } else { $lines.Add($line) }
    }
    if (-not $found) { $lines.Add("$Key=$Value") }
    [IO.File]::WriteAllLines($Path, $lines, [Text.UTF8Encoding]::new($false))
}

function Ensure-Secret([string]$Path, [string]$Key) {
    $value = Get-EnvValue $Path $Key
    if (-not $value) { $value = New-RandomHex; Set-EnvValue $Path $Key $value }
    if ($Key -eq 'UPDATE_AGENT_TOKEN' -and $value.Length -lt 32) { throw "$Key должен содержать не менее 32 символов." }
    return $value
}

function Protect-EnvironmentFile([string]$Path, [string]$WebIdentity) {
    $acl = Get-Acl -LiteralPath $Path
    $acl.SetAccessRuleProtection($true, $false)
    foreach ($rule in @($acl.Access)) { $acl.RemoveAccessRuleSpecific($rule) }
    $identities = @(
        [Security.Principal.WindowsIdentity]::GetCurrent().User,
        [Security.Principal.SecurityIdentifier]::new('S-1-5-18'),
        [Security.Principal.SecurityIdentifier]::new('S-1-5-32-544')
    )
    foreach ($identity in $identities) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($identity, 'FullControl', 'Allow'))
    }
    if ($WebIdentity -and $WebIdentity -notin @('LocalSystem', 'NT AUTHORITY\SYSTEM')) {
        $acl.AddAccessRule([Security.AccessControl.FileSystemAccessRule]::new($WebIdentity, 'Modify', 'Allow'))
    }
    Set-Acl -LiteralPath $Path -AclObject $acl
}

function Grant-WebAccess([string]$Path, [string]$WebIdentity) {
    if (-not $WebIdentity -or $WebIdentity -in @('LocalSystem', 'NT AUTHORITY\SYSTEM')) { return }
    & icacls.exe $Path /grant "${WebIdentity}:(OI)(CI)RX" /T /C | Out-Null
    if ($LASTEXITCODE -ne 0) { throw 'Не удалось предоставить веб-серверу доступ к файлам проекта.' }
    foreach ($writable in @('storage', 'bootstrap\cache', 'public\uploads')) {
        $directory = Join-Path $Path $writable
        New-Item -ItemType Directory -Force -Path $directory | Out-Null
        & icacls.exe $directory /grant "${WebIdentity}:(OI)(CI)M" /T /C | Out-Null
        if ($LASTEXITCODE -ne 0) { throw "Не удалось открыть веб-серверу запись в $writable." }
    }
}

function Read-HttpPort([string]$Default) {
    $answer = Read-Host "Порт сайта [$Default]"
    $value = if ($answer) { $answer } else { $Default }
    $number = 0
    if (-not [int]::TryParse($value, [ref]$number) -or $number -lt 1 -or $number -gt 65535) {
        throw 'Порт должен быть числом от 1 до 65535.'
    }
    return $number
}

function Confirm-DockerImages([string]$ProjectPath, [string]$DockerPath) {
    $images = [Collections.Generic.HashSet[string]]::new([StringComparer]::OrdinalIgnoreCase)
    foreach ($relativePath in @('Dockerfile', 'update-agent\Dockerfile')) {
        $dockerfile = Join-Path $ProjectPath $relativePath
        foreach ($line in Get-Content -LiteralPath $dockerfile) {
            if ($line -match '^\s*FROM\s+([^\s]+)' -and $Matches[1] -ne 'scratch') {
                $null = $images.Add($Matches[1])
            } elseif ($line -match '^\s*COPY\s+--from=([^\s]+)' -and $Matches[1] -match '[:/]') {
                $null = $images.Add($Matches[1])
            }
        }
    }
    foreach ($line in Get-Content -LiteralPath (Join-Path $ProjectPath 'compose.yaml')) {
        if ($line -match '^\s*image:\s*([^\s#]+)' -and $Matches[1] -notmatch '\$') {
            $null = $images.Add($Matches[1])
        }
    }
    if ($images.Count -eq 0) { throw 'Не удалось определить Docker-образы проекта. Проверьте Dockerfile и compose.yaml.' }
    $missing = @()
    Write-Host 'Проверяю наличие Docker-образов на компьютере...'
    foreach ($image in ($images | Sort-Object)) {
        & $DockerPath image inspect $image *> $null
        if ($LASTEXITCODE -eq 0) { Write-Host "Есть: $image" }
        else { Write-Host "Нужно скачать: $image"; $missing += $image }
    }
    if ($missing.Count -gt 0) {
        $answer = Read-Host "Отсутствует образов: $($missing.Count). Разрешить Docker скачать их при запуске? Y/N [N]"
        if ($answer -notmatch '^[Yy]$') { throw 'Установка остановлена до скачивания Docker-образов.' }
    }
    Write-Host 'При первой сборке Docker также может скачать пакеты внутри образов.'
}

function Install-MissingWindowsComponents {
    $missing = @()
    foreach ($item in @(@('php.exe', 'PHP.PHP.8.4'), @('python.exe', 'Python.Python.3.13'), @('git.exe', 'Git.Git'))) {
        if (-not (Get-Command $item[0] -ErrorAction SilentlyContinue)) { $missing += ,$item }
    }
    $needsComposer = -not (Get-Command composer.bat -ErrorAction SilentlyContinue) -and -not (Get-Command composer.exe -ErrorAction SilentlyContinue)
    $appcmdPath = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
    $needsIis = -not (Test-Path $appcmdPath)
    if ($missing.Count -eq 0 -and -not $needsIis -and -not $needsComposer) { return }
    $names = @($missing | ForEach-Object { $_[0] })
    if ($needsIis) { $names += 'IIS' }
    if ($needsComposer) { $names += 'Composer' }
    $answer = Read-Host "Отсутствуют $($names -join ', '). Установить через winget/компоненты Windows? Y/N [N]"
    if ($answer -notmatch '^[Yy]$') { throw 'Для установки без Docker необходимы перечисленные компоненты.' }
    if ($needsIis) {
        Enable-WindowsOptionalFeature -Online -FeatureName IIS-WebServerRole, IIS-WebServer, IIS-CGI -All -NoRestart | Out-Null
        if (-not (Test-Path $appcmdPath)) { throw 'IIS установлен, но для продолжения может потребоваться перезагрузка Windows.' }
    }
    if ($missing.Count -gt 0) {
        $winget = Get-Command winget.exe -ErrorAction SilentlyContinue
        if (-not $winget) { throw 'WinGet недоступен. Установите Windows App Installer и повторите запуск.' }
        foreach ($item in $missing) {
            & $winget.Source install --exact --id $item[1] --source winget --accept-source-agreements --accept-package-agreements --disable-interactivity
            if ($LASTEXITCODE -ne 0) { throw "Не удалось установить $($item[0]) через winget ($($item[1]))." }
        }
        $env:PATH = [Environment]::GetEnvironmentVariable('PATH', 'Machine') + ';' + [Environment]::GetEnvironmentVariable('PATH', 'User')
    }
    if ($needsComposer) {
        $php = Get-Command php.exe -ErrorAction SilentlyContinue
        if (-not $php) { throw 'После установки PHP не найден в PATH. Повторите установку после входа в Windows.' }
        $composerDir = Join-Path $env:ProgramData 'PhotoAuthApp\tools'
        New-Item -ItemType Directory -Force -Path $composerDir | Out-Null
        $installerPath = Join-Path $env:TEMP 'photoauthapp-composer-setup.php'
        try {
            $signature = (Invoke-WebRequest 'https://composer.github.io/installer.sig' -UseBasicParsing -TimeoutSec 30).Content.Trim()
            Invoke-WebRequest 'https://getcomposer.org/installer' -UseBasicParsing -TimeoutSec 60 -OutFile $installerPath
            $digest = (Get-FileHash -LiteralPath $installerPath -Algorithm SHA384).Hash
            if ($digest -ne $signature) { throw 'Контрольная сумма установщика Composer не совпала.' }
            & $php.Source $installerPath --install-dir=$composerDir --filename=composer.phar --quiet
            if ($LASTEXITCODE -ne 0) { throw 'Не удалось установить Composer.' }
        } finally { Remove-Item -LiteralPath $installerPath -ErrorAction SilentlyContinue }
        $composerBat = Join-Path $composerDir 'composer.bat'
        [IO.File]::WriteAllText($composerBat, ('@echo off' + "`r`n" + '"' + $php.Source + '" "%~dp0composer.phar" %*' + "`r`n"), [Text.Encoding]::ASCII)
        $env:PATH = $composerDir + ';' + $env:PATH
    }
}

if ($Mode -eq 'host') {
    $identity = [Security.Principal.WindowsIdentity]::GetCurrent()
    $principal = [Security.Principal.WindowsPrincipal]::new($identity)
    if (-not $principal.IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
        $powershell = Join-Path $env:SystemRoot 'System32\WindowsPowerShell\v1.0\powershell.exe'
        $child = Start-Process -FilePath $powershell -Verb RunAs -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File `"$PSCommandPath`" -Mode host" -Wait -PassThru
        exit $child.ExitCode
    }
}

if ($Mode -eq 'docker') { Confirm-DockerImages $appDir (Assert-DockerReady) }

$envName = if ($Mode -eq 'docker' -and -not (Test-Path (Join-Path $appDir '.env')) -and (Test-Path (Join-Path $appDir '.env.production'))) { '.env.production' } else { '.env' }
if ($Mode -eq 'docker' -and (Test-Path (Join-Path $appDir '.env')) -and (Test-Path (Join-Path $appDir '.env.production'))) {
    $envChoice = Read-Host 'Найдены .env и .env.production. Использовать .env.production? Y/N [N]'
    if ($envChoice -match '^[Yy]$') { $envName = '.env.production' }
}
$envFile = Join-Path $appDir $envName
if (-not (Test-Path $envFile)) {
    $template = if ($envName -eq '.env.production') { '.env.example.production' } else { '.env.example' }
    Copy-Item -LiteralPath (Join-Path $appDir $template) -Destination $envFile
}
$appKey = Get-EnvValue $envFile 'APP_KEY'
if (-not $appKey) {
    $bytes = New-Object byte[] 32
    $generator = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $generator.GetBytes($bytes) } finally { $generator.Dispose() }
    Set-EnvValue $envFile 'APP_KEY' ('base64:' + [Convert]::ToBase64String($bytes))
}
$setupKey = Ensure-Secret $envFile 'SETUP_ACCESS_KEY'
$null = Ensure-Secret $envFile 'API_TOKEN'
$null = Ensure-Secret $envFile 'UPDATE_AGENT_TOKEN'

if ($Mode -eq 'docker') {
    $savedPort = Get-EnvValue $envFile 'APP_PORT'
    if (-not $savedPort) { $savedPort = '27020' }
    $port = Read-HttpPort $savedPort
    Set-EnvValue $envFile 'APP_PORT' "$port"
    $defaultUrl = Get-EnvValue $envFile 'APP_URL'
    if ($defaultUrl -notmatch '^https?://[^\s/]+(?::\d+)?$' -or $defaultUrl -match 'SERVER_IP_OR_DOMAIN') { $defaultUrl = "http://localhost:$port" }
    else { $urlBuilder = [UriBuilder]::new($defaultUrl); $urlBuilder.Port = $port; $defaultUrl = $urlBuilder.Uri.GetLeftPart([UriPartial]::Authority) }
    $chosenUrl = Read-Host "Адрес приложения в браузере [$defaultUrl]"
    $appUrl = if ($chosenUrl) { $chosenUrl } else { $defaultUrl }
    if ($appUrl -notmatch '^https?://[^\s/]+(?::\d+)?$') { throw 'Укажите полный адрес без пути.' }
    Set-EnvValue $envFile 'APP_URL' $appUrl
    Set-EnvValue $envFile 'UPDATE_RUNTIME' 'docker'
    Set-EnvValue $envFile 'UPDATE_AGENT_URL' 'http://updater:19100'
    Set-EnvValue $envFile 'APP_ENV_FILE' $envName
    Set-EnvValue $envFile 'APP_ENV' 'production'
    Set-EnvValue $envFile 'APP_DEBUG' 'false'
    Protect-EnvironmentFile $envFile ''
    & docker compose --profile updates --env-file $envFile -f (Join-Path $appDir 'compose.yaml') up -d --build
    if ($LASTEXITCODE -ne 0) { throw 'Не удалось запустить PhotoAuthApp и агент через Docker Compose.' }
} else {
    Install-MissingWindowsComponents
    $port = Read-HttpPort $(if (Get-EnvValue $envFile 'APP_PORT') { Get-EnvValue $envFile 'APP_PORT' } else { '27020' })
    $agentPort = Read-HttpPort $(if (Get-EnvValue $envFile 'UPDATE_AGENT_PORT') { Get-EnvValue $envFile 'UPDATE_AGENT_PORT' } else { '19100' })
    if ($agentPort -eq $port) { throw 'Порты сайта и агента должны различаться.' }
    $defaultUrl = "http://localhost:$port"
    $enteredUrl = Read-Host "Адрес приложения в браузере [$defaultUrl]"
    $appUrl = if ($enteredUrl) { $enteredUrl } else { $defaultUrl }
    if ($appUrl -notmatch '^http://[^\s/]+(?::\d+)?$') { throw 'Укажите HTTP-адрес без пути, например http://localhost:27020.' }
    if (([uri]$appUrl).Port -ne $port) { throw 'Порт в адресе приложения должен совпадать с выбранным портом сайта.' }
    $kind = 'iis'
    $target = 'PhotoAuthApp'
    foreach ($program in @('php.exe', 'python.exe', 'git.exe')) {
        if (-not (Get-Command $program -ErrorAction SilentlyContinue)) { throw "Не найдена обязательная программа: $program" }
    }
    $composerCommand = Get-Command composer.bat -ErrorAction SilentlyContinue
    if (-not $composerCommand) { $composerCommand = Get-Command composer.exe -ErrorAction SilentlyContinue }
    if (-not $composerCommand) { throw 'Не найден Composer.' }
    $appcmd = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
    if (-not (Test-Path $appcmd)) { throw 'Для запуска без Docker необходим IIS. Его компоненты не найдены.' }
    $rewriteModule = & $appcmd list module /name:RewriteModule
    if ($LASTEXITCODE -ne 0 -or -not $rewriteModule) {
        $answer = Read-Host 'Не найден IIS URL Rewrite. Установить через winget? Y/N [N]'
        if ($answer -notmatch '^[Yy]$') { throw 'Для адресов PhotoAuthApp необходим IIS URL Rewrite.' }
        $winget = Get-Command winget.exe -ErrorAction SilentlyContinue
        if (-not $winget) { throw 'WinGet недоступен для установки IIS URL Rewrite.' }
        & $winget.Source install --exact --name 'IIS URL Rewrite' --source winget --accept-source-agreements --accept-package-agreements --disable-interactivity
        if ($LASTEXITCODE -ne 0) { throw 'Пакет IIS URL Rewrite не удалось установить через winget.' }
        $rewriteModule = & $appcmd list module /name:RewriteModule
        if ($LASTEXITCODE -ne 0 -or -not $rewriteModule) { throw 'IIS URL Rewrite не зарегистрирован после установки.' }
    }
    $phpPath = (Get-Command php.exe -ErrorAction Stop).Source
    $phpCgiPath = Join-Path (Split-Path -Parent $phpPath) 'php-cgi.exe'
    if (-not (Test-Path $phpCgiPath)) { throw "Не найден php-cgi.exe рядом с $phpPath." }
    $fastCgi = (& $appcmd list config /section:system.webServer/fastCgi) -join "`n"
    if ($fastCgi -notmatch [regex]::Escape($phpCgiPath)) {
        & $appcmd set config '-section:system.webServer/fastCgi' "/+[fullPath='$phpCgiPath']" '/commit:apphost' | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Не удалось зарегистрировать PHP FastCGI в IIS.' }
    }
    $existingSite = & $appcmd list site /name:$target
    if ($existingSite -and $existingSite -notmatch [regex]::Escape("*:${port}:")) { throw "Сайт IIS $target уже существует с другой привязкой. Измените её вручную или выберите свободное имя/порт." }
    if ($existingSite) {
        $physicalPath = (& $appcmd list vdir "$target/" /text:physicalPath).Trim().Trim('"')
        if ([IO.Path]::GetFullPath($physicalPath) -ne [IO.Path]::GetFullPath((Join-Path $appDir 'public'))) {
            throw "Сайт IIS $target уже привязан к другому проекту: $physicalPath"
        }
    }
    if (-not $existingSite) {
        $listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue
        if ($listener) { throw "Порт $port уже занят. Выберите другой порт." }
    }
    Set-EnvValue $envFile 'APP_PORT' "$port"
    Set-EnvValue $envFile 'APP_URL' $appUrl
    Set-EnvValue $envFile 'UPDATE_RUNTIME' 'host'
    Set-EnvValue $envFile 'UPDATE_AGENT_PORT' "$agentPort"
    Set-EnvValue $envFile 'UPDATE_AGENT_URL' "http://127.0.0.1:$agentPort"
    Set-EnvValue $envFile 'APP_ENV' 'production'
    Set-EnvValue $envFile 'APP_DEBUG' 'false'
    $webIdentity = "IIS AppPool\$target"
    & $appcmd list apppool "/name:$target" | Out-Null
    if ($LASTEXITCODE -ne 0) {
        & $appcmd add apppool "/name:$target" "/managedRuntimeVersion:" | Out-Null
        if ($LASTEXITCODE -ne 0) { throw 'Не удалось создать пул IIS.' }
    }
    Protect-EnvironmentFile $envFile $webIdentity
    Grant-WebAccess $appDir $webIdentity
    Push-Location $appDir
    try {
        & $composerCommand.Source install --no-dev --no-interaction --prefer-dist --optimize-autoloader
        if ($LASTEXITCODE -ne 0) { throw 'Не удалось установить зависимости приложения.' }
        & php.exe artisan storage:link
        if (-not $existingSite) {
            & $appcmd add site "/name:$target" "/bindings:http/*:${port}:" "/physicalPath:$(Join-Path $appDir 'public')" | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Не удалось создать сайт IIS.' }
            & $appcmd set app "$target/" "/applicationPool:$target" | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Не удалось назначить пул приложений сайту IIS.' }
        }
        $siteHandlers = (& $appcmd list config $target /section:system.webServer/handlers) -join "`n"
        if ($siteHandlers -notmatch [regex]::Escape($phpCgiPath)) {
            & $appcmd set config $target '-section:system.webServer/handlers' "/+[name='PhotoAuthApp-PHP',path='*.php',verb='*',modules='FastCgiModule',scriptProcessor='$phpCgiPath',resourceType='Either']" | Out-Null
            if ($LASTEXITCODE -ne 0) { throw 'Не удалось подключить PHP FastCGI к сайту IIS.' }
        }
        & $appcmd start apppool "/apppool.name:$target" | Out-Null
        & $appcmd start site "/site.name:$target" | Out-Null
        & (Join-Path $appDir 'Install-PhotoAuthUpdater.ps1') -ProjectDirectory $appDir -EnvironmentFile $envFile -ComposerExecutable $composerCommand.Source -AgentPort $agentPort -RestartKind $kind -RestartTarget $target -HealthUrl "$appUrl/login"
    } finally { Pop-Location }
}

$ready = $false
for ($attempt = 0; $attempt -lt 12; $attempt++) {
    try {
        $page = Invoke-WebRequest -Uri "$appUrl/setup/auth" -UseBasicParsing -TimeoutSec 5
        if ($page.StatusCode -eq 200 -and $page.Content -match 'data-photoauth-setup') { $ready = $true; break }
    } catch { }
    Start-Sleep -Seconds 2
}
if (-not $ready) { throw 'Страница PhotoAuthApp не открылась. Проверьте адрес приложения и настройку веб-сервера для папки public.' }
Write-Host "PhotoAuthApp: $appUrl/setup/auth"
Write-Host "Ключ первоначальной настройки (сохраните до завершения): $setupKey"
Start-Process "$appUrl/setup/auth"
Read-Host 'Сохраните ключ первоначальной настройки и нажмите Enter'
