param([Parameter(Mandatory = $true)][string]$ConfigPath)
$ErrorActionPreference = 'Stop'
$settings = Get-Content -LiteralPath $ConfigPath -Raw | ConvertFrom-Json
foreach ($entry in $settings.PSObject.Properties) {
    [Environment]::SetEnvironmentVariable($entry.Name, [string]$entry.Value, 'Process')
}
$env:GIT_CONFIG_COUNT = '1'
$env:GIT_CONFIG_KEY_0 = 'safe.directory'
$env:GIT_CONFIG_VALUE_0 = $settings.PHOTOAUTHAPP_DIR
$python = $settings.PYTHON_EXE
& $python -u (Join-Path $settings.PHOTOAUTHAPP_DIR 'update-agent\server.py')
exit $LASTEXITCODE
