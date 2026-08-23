# Copia la base de datos de producción a finanzas_dev (Windows / PowerShell).
# Uso: .\scripts\db-sync.ps1 [-Force]
#
# Requiere: mysql y mysqldump en PATH (MySQL client tools).

param(
    [switch]$Force
)

$ErrorActionPreference = "Stop"
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$ProjectDir = Split-Path -Parent $ScriptDir
$EnvFile = Join-Path $ScriptDir ".env.sync"

if (-not (Test-Path $EnvFile)) {
    Write-Error "No existe $EnvFile. Copiá .env.sync.example a .env.sync y completá credenciales."
}

Get-Content $EnvFile | ForEach-Object {
    if ($_ -match '^\s*([^#=]+)=(.*)$') {
        $name = $matches[1].Trim()
        $value = $matches[2].Trim()
        Set-Variable -Name $name -Value $value -Scope Script
    }
}

$required = @(
    "SYNC_SOURCE_HOST", "SYNC_SOURCE_DATABASE", "SYNC_SOURCE_USER", "SYNC_SOURCE_PASSWORD",
    "SYNC_TARGET_HOST", "SYNC_TARGET_DATABASE", "SYNC_TARGET_USER", "SYNC_TARGET_PASSWORD",
    "SYNC_DEV_ADMIN_USER", "SYNC_DEV_ADMIN_PASSWORD"
)
foreach ($r in $required) {
    if (-not (Get-Variable -Name $r -ErrorAction SilentlyContinue)) {
        Write-Error "Variable $r no definida en .env.sync"
    }
}

if (-not $SYNC_SOURCE_PORT) { $SYNC_SOURCE_PORT = "3306" }
if (-not $SYNC_TARGET_PORT) { $SYNC_TARGET_PORT = "3306" }
if (-not $SYNC_COMPRESS) { $SYNC_COMPRESS = "1" }
if (-not $SYNC_TMP_DIR) { $SYNC_TMP_DIR = Join-Path $ProjectDir "storage\tmp" }

New-Item -ItemType Directory -Force -Path $SYNC_TMP_DIR | Out-Null

$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$dumpBase = Join-Path $SYNC_TMP_DIR "finanzas_$timestamp.sql"
$dumpFile = if ($SYNC_COMPRESS -eq "1") { "$dumpBase.gz" } else { $dumpBase }

Write-Host "==> Origen:  ${SYNC_SOURCE_USER}@${SYNC_SOURCE_HOST}:${SYNC_SOURCE_PORT}/${SYNC_SOURCE_DATABASE}"
Write-Host "==> Destino: ${SYNC_TARGET_USER}@${SYNC_TARGET_HOST}:${SYNC_TARGET_PORT}/${SYNC_TARGET_DATABASE}"
Write-Host "==> Dump:    $dumpFile"

if (-not $Force) {
    $ans = Read-Host "¿Continuar? Se SOBRESCRIBIRÁ la base destino. [y/N]"
    if ($ans -notmatch '^[Yy]$') {
        Write-Host "Cancelado."
        exit 0
    }
}

$ignoreArgs = @()
if ($SYNC_SKIP_TABLES) {
    foreach ($t in ($SYNC_SKIP_TABLES -split ',')) {
        $t = $t.Trim()
        if ($t) { $ignoreArgs += "--ignore-table=${SYNC_SOURCE_DATABASE}.$t" }
    }
}

$start = Get-Date
Write-Host "==> Exportando..."

$env:MYSQL_PWD = $SYNC_SOURCE_PASSWORD
$dumpCmd = @(
    "mysqldump",
    "--host=$SYNC_SOURCE_HOST",
    "--port=$SYNC_SOURCE_PORT",
    "--user=$SYNC_SOURCE_USER",
    "--single-transaction",
    "--quick",
    "--routines",
    "--triggers",
    "--set-gtid-purged=OFF",
    "--default-character-set=utf8mb4"
) + $ignoreArgs + @($SYNC_SOURCE_DATABASE)

if ($SYNC_COMPRESS -eq "1") {
    & $dumpCmd[0] $dumpCmd[1..($dumpCmd.Length - 1)] | gzip > $dumpFile
} else {
    & $dumpCmd[0] $dumpCmd[1..($dumpCmd.Length - 1)] | Out-File -FilePath $dumpFile -Encoding utf8
}

Write-Host "==> Recreando base destino..."
$env:MYSQL_PWD = $SYNC_TARGET_PASSWORD
$sql = "DROP DATABASE IF EXISTS ``$SYNC_TARGET_DATABASE``; CREATE DATABASE ``$SYNC_TARGET_DATABASE`` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql --host=$SYNC_TARGET_HOST --port=$SYNC_TARGET_PORT --user=$SYNC_TARGET_USER -e $sql

Write-Host "==> Importando..."
if ($SYNC_COMPRESS -eq "1") {
    gunzip -c $dumpFile | mysql --host=$SYNC_TARGET_HOST --port=$SYNC_TARGET_PORT --user=$SYNC_TARGET_USER $SYNC_TARGET_DATABASE
} else {
    Get-Content $dumpFile | mysql --host=$SYNC_TARGET_HOST --port=$SYNC_TARGET_PORT --user=$SYNC_TARGET_USER $SYNC_TARGET_DATABASE
}

Write-Host "==> Post-restore (password dev)..."
if (Get-Command php -ErrorAction SilentlyContinue) {
    $devHash = php -r "echo password_hash('$SYNC_DEV_ADMIN_PASSWORD', PASSWORD_BCRYPT);"
    $postSql = Join-Path $SYNC_TMP_DIR "post_$timestamp.sql"
    (Get-Content (Join-Path $ScriptDir "db-sync-post.sql")) `
        -replace '@dev_password_hash@', $devHash `
        -replace '@dev_admin_user@', $SYNC_DEV_ADMIN_USER |
        Set-Content $postSql
    Get-Content $postSql | mysql --host=$SYNC_TARGET_HOST --port=$SYNC_TARGET_PORT --user=$SYNC_TARGET_USER $SYNC_TARGET_DATABASE
    Remove-Item $postSql -ErrorAction SilentlyContinue
} else {
    Write-Warning "php no encontrado; saltando reset de password."
}

$elapsed = ((Get-Date) - $start).TotalSeconds
Write-Host "==> Listo en $([math]::Round($elapsed))s"
Write-Host "    Usuario dev: $SYNC_DEV_ADMIN_USER"
Write-Host "    Password:    $SYNC_DEV_ADMIN_PASSWORD"

mysql --host=$SYNC_TARGET_HOST --port=$SYNC_TARGET_PORT --user=$SYNC_TARGET_USER $SYNC_TARGET_DATABASE -e @"
SELECT 'asientos' AS tabla, COUNT(*) AS n FROM asientos
UNION SELECT 'ingresos', COUNT(*) FROM ingresos
UNION SELECT 'pagos', COUNT(*) FROM pagos
UNION SELECT 'cuentas', COUNT(*) FROM cuentas;
"@
