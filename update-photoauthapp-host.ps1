$ErrorActionPreference = 'Stop'
$appDir = if ($env:APP_DIR) { $env:APP_DIR } else { $env:PHOTOAUTHAPP_DIR }
$targetSha = $env:TARGET_SHA
$currentSha = $env:CURRENT_SHA
$runMigrations = $env:RUN_MIGRATIONS -eq '1'
$stateDir = if ($env:UPDATE_STATE_DIR) { $env:UPDATE_STATE_DIR } else { Join-Path $appDir 'storage\app\update-agent' }
$restartKind = $env:UPDATE_RESTART_KIND
$restartTarget = $env:UPDATE_RESTART_TARGET
$healthUrl = if ($env:UPDATE_HEALTH_URL) { $env:UPDATE_HEALTH_URL } else { 'http://127.0.0.1/login' }

if (-not $appDir -or -not (Test-Path (Join-Path $appDir '.git'))) { throw 'Не задан каталог Git-проекта для агента ОС.' }
if (-not $restartTarget -or $restartKind -notin @('iis', 'service')) { throw 'Не настроен способ перезапуска IIS или службы Windows.' }
Set-Location $appDir
$dirty = & $env:GIT_EXE -c "safe.directory=$appDir" status --porcelain --untracked-files=all
if ($LASTEXITCODE -ne 0 -or $dirty) { throw 'Обновление остановлено: в проекте есть локальные изменения или файлы.' }
$sourceHead = (& $env:GIT_EXE -c "safe.directory=$appDir" rev-parse HEAD).Trim()
if ($LASTEXITCODE -ne 0) { throw 'Не удалось определить текущую версию проекта.' }
$current = if ($currentSha) { $currentSha } else { $sourceHead }
& $env:GIT_EXE -c "safe.directory=$appDir" merge-base --is-ancestor $sourceHead $targetSha
if ($LASTEXITCODE -ne 0) { throw 'Целевая версия не является продолжением текущей ветки.' }
$migrations = @(& $env:GIT_EXE -c "safe.directory=$appDir" diff --name-only $current $targetSha -- database/migrations)
if ($LASTEXITCODE -ne 0) { throw 'Не удалось проверить список миграций.' }
if ($migrations.Count -gt 0 -and -not $runMigrations) { throw 'Для миграций требуется отдельное подтверждение.' }

function Restart-PhotoAuthWeb {
    if ($restartKind -eq 'iis') {
        $appcmd = Join-Path $env:windir 'System32\inetsrv\appcmd.exe'
        if (-not (Test-Path $appcmd)) { throw 'IIS appcmd.exe не найден.' }
        & $appcmd recycle apppool "/apppool.name:$restartTarget"
        if ($LASTEXITCODE -ne 0) { throw "Не удалось перезапустить пул IIS $restartTarget." }
    } else {
        Restart-Service -Name $restartTarget -Force
    }
}

function Restore-PhotoAuthCode {
    & $env:GIT_EXE -c "safe.directory=$appDir" reset --hard $sourceHead
    if (Test-Path 'composer.lock') { & $env:COMPOSER_EXE install --no-dev --no-interaction --prefer-dist --optimize-autoloader }
    Restart-PhotoAuthWeb
}

& $env:GIT_EXE -c "safe.directory=$appDir" merge --ff-only $targetSha
if ($LASTEXITCODE -ne 0) { throw 'Не удалось обновить файлы проекта.' }
if (Test-Path 'composer.lock') {
    & $env:COMPOSER_EXE install --no-dev --no-interaction --prefer-dist --optimize-autoloader
    if ($LASTEXITCODE -ne 0) { Restore-PhotoAuthCode; throw 'Не удалось установить зависимости Composer.' }
}

if ($migrations.Count -gt 0) {
    $backupDir = Join-Path $stateDir 'schema-backups'
    New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
    $backupFile = Join-Path $backupDir ('schema-' + (Get-Date).ToUniversalTime().ToString('yyyyMMddTHHmmssZ') + '.sql')
    & $env:PHP_EXE update-agent/dump-schema.php $backupFile
    if ($LASTEXITCODE -ne 0 -or -not (Test-Path $backupFile) -or (Get-Item $backupFile).Length -eq 0) {
        Remove-Item -LiteralPath $backupFile -Force -ErrorAction SilentlyContinue
        Restore-PhotoAuthCode
        throw 'Не удалось сохранить схему MySQL; миграции не запускались.'
    }
    'PHOTOAUTH_MIGRATION_STARTED'
    & $env:PHP_EXE artisan migrate --force
    if ($LASTEXITCODE -ne 0) {
        try { Restart-PhotoAuthWeb } catch { }
        throw 'Миграция завершилась ошибкой; проверьте приложение и базу вручную.'
    }
}

Restart-PhotoAuthWeb
$healthy = $false
for ($attempt = 0; $attempt -lt 18; $attempt++) {
    try {
        $response = Invoke-WebRequest -Uri $healthUrl -TimeoutSec 5 -UseBasicParsing
        if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) { $healthy = $true; break }
    } catch { }
    Start-Sleep -Seconds 5
}
if (-not $healthy) {
    if ($migrations.Count -eq 0) { Restore-PhotoAuthCode }
    throw 'Проверка приложения не прошла после обновления.'
}
$newSha = (& $env:GIT_EXE -c "safe.directory=$appDir" rev-parse --short HEAD).Trim()
"Обновление завершено: $newSha"
