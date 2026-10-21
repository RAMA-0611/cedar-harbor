#Requires -Version 5.1
<#
.SYNOPSIS
  Restore a custom-format PostgreSQL dump into a target database.
.NOTES
  Refuses target=rotor (or DB_DATABASE from env) unless -AllowDestructiveRestore
  and -ConfirmDestructive are both passed. Never prints passwords.

  The application role `rotor` typically cannot CREATE DATABASE. Prefer:
    1) create the target DB as a superuser, then restore; or
    2) pass PG_ADMIN_USERNAME / PG_ADMIN_PASSWORD in the environment
       (or -AdminUsername) only for CREATE DATABASE.
#>
[CmdletBinding()]
param(
    [Parameter(Mandatory = $true)]
    [string]$BackupPath,

    [Parameter(Mandatory = $true)]
    [string]$TargetDatabase,

    [string]$EnvFile,
    [string]$PgBin = 'C:\Program Files\PostgreSQL\16\bin',
    [string]$AdminUsername,
    [switch]$AllowDestructiveRestore,
    [switch]$ConfirmDestructive,
    [switch]$CreateTargetIfMissing,
    [switch]$Clean
)

Set-StrictMode -Version Latest
$ErrorActionPreference = 'Stop'

$scriptDir = if ($PSScriptRoot) { $PSScriptRoot } else { Split-Path -Parent $MyInvocation.MyCommand.Path }
if (-not $EnvFile) {
    $EnvFile = Join-Path $scriptDir '..\backend\.env'
}

function Get-DotEnvMap([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path)) {
        throw "Env file not found: $Path"
    }
    $map = @{}
    Get-Content -LiteralPath $Path | ForEach-Object {
        $line = $_.Trim()
        if ($line -eq '' -or $line.StartsWith('#') -or $line -notmatch '=') { return }
        $parts = $line.Split('=', 2)
        $key = $parts[0].Trim()
        $value = $parts[1].Trim().Trim('"').Trim("'")
        if ($value -eq 'null') { $value = '' }
        $map[$key] = $value
    }
    return $map
}

function Invoke-PsqlText {
    param(
        [string]$PsqlPath,
        [string]$DbHost,
        [string]$DbPort,
        [string]$Username,
        [string]$Database,
        [string]$Sql
    )
    $raw = & $PsqlPath -h $DbHost -p $DbPort -U $Username -d $Database -Atc $Sql 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "psql failed (exit $LASTEXITCODE): $raw"
    }
    return ([string]($raw | Out-String)).Trim()
}

if ($TargetDatabase -notmatch '^[A-Za-z_][A-Za-z0-9_]*$') {
    throw "Invalid target database name."
}

if (-not (Test-Path -LiteralPath $BackupPath)) {
    throw "Backup not found: $BackupPath"
}
if ((Get-Item -LiteralPath $BackupPath).Length -le 0) {
    throw "Backup file is empty: $BackupPath"
}

$envMap = Get-DotEnvMap $EnvFile
$dbHost = if ($envMap['DB_HOST']) { $envMap['DB_HOST'] } else { '127.0.0.1' }
$dbPort = if ($envMap['DB_PORT']) { $envMap['DB_PORT'] } else { '5432' }
$dbUser = if ($envMap['DB_USERNAME']) { $envMap['DB_USERNAME'] } else { 'rotor' }
$protected = if ($envMap['DB_DATABASE']) { $envMap['DB_DATABASE'] } else { 'rotor' }

if (-not $AdminUsername) {
    $AdminUsername = [string]$env:PG_ADMIN_USERNAME
}

$localHosts = @('127.0.0.1', 'localhost', '::1')
if ($localHosts -notcontains $dbHost) {
    throw "Refusing restore: DB_HOST='$dbHost' is not local."
}

if ($TargetDatabase -eq $protected -and -not $AllowDestructiveRestore) {
    throw "Refusing restore onto protected database '$protected'. Pass -AllowDestructiveRestore (and confirm) only for intentional destructive restore."
}

if ($TargetDatabase -eq $protected -and $AllowDestructiveRestore -and -not $ConfirmDestructive) {
    throw "Destructive restore onto '$protected' requires both -AllowDestructiveRestore and -ConfirmDestructive."
}

$psql = Join-Path $PgBin 'psql.exe'
$pgRestore = Join-Path $PgBin 'pg_restore.exe'
if (-not (Test-Path -LiteralPath $psql) -or -not (Test-Path -LiteralPath $pgRestore)) {
    throw "psql/pg_restore not found under $PgBin"
}

$previousPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = [string]$envMap['DB_PASSWORD']
    $exists = Invoke-PsqlText -PsqlPath $psql -DbHost $dbHost -DbPort $dbPort -Username $dbUser -Database 'postgres' -Sql "SELECT COUNT(*) FROM pg_database WHERE datname = '$TargetDatabase'"
    if ($exists -ne '1') {
        if (-not $CreateTargetIfMissing) {
            throw "Target database '$TargetDatabase' does not exist. Recreate with -CreateTargetIfMissing or create it manually as a superuser."
        }

        $createUser = $dbUser
        $createPassword = [string]$envMap['DB_PASSWORD']
        if ($AdminUsername) {
            $createUser = $AdminUsername
            if ($env:PG_ADMIN_PASSWORD) {
                $createPassword = [string]$env:PG_ADMIN_PASSWORD
            }
        }

        $env:PGPASSWORD = $createPassword
        $createSql = "CREATE DATABASE `"$TargetDatabase`" OWNER `"$dbUser`";"
        $createOut = & $psql -h $dbHost -p $dbPort -U $createUser -d postgres -c $createSql 2>&1
        if ($LASTEXITCODE -ne 0) {
            throw "CREATE DATABASE failed (exit $LASTEXITCODE). The application role may lack CREATEDB; create '$TargetDatabase' as a superuser and retry without -CreateTargetIfMissing. $createOut"
        }
        Write-Output "CREATED_DATABASE=$TargetDatabase"
        $env:PGPASSWORD = [string]$envMap['DB_PASSWORD']
    } elseif ($TargetDatabase -eq $protected -and $AllowDestructiveRestore) {
        Write-Output "DESTRUCTIVE_RESTORE_CONFIRMED=$TargetDatabase"
    }

    $env:PGPASSWORD = [string]$envMap['DB_PASSWORD']
    $restoreArgs = @('-h', $dbHost, '-p', $dbPort, '-U', $dbUser, '-d', $TargetDatabase, '--no-owner', '--no-acl')
    if ($Clean -or ($TargetDatabase -eq $protected -and $AllowDestructiveRestore)) {
        $restoreArgs += @('--clean', '--if-exists')
    }
    $restoreArgs += $BackupPath
    $restoreOut = & $pgRestore @restoreArgs 2>&1
    # pg_restore returns 1 when some objects warn (e.g. --clean of missing objects); 0 is clean success.
    if ($LASTEXITCODE -gt 1) {
        throw "pg_restore failed with exit code $LASTEXITCODE : $restoreOut"
    }
} finally {
    if ($null -eq $previousPassword) {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    } else {
        $env:PGPASSWORD = $previousPassword
    }
}

Write-Output "RESTORE_TARGET=$TargetDatabase"
Write-Output "RESTORE_BACKUP=$BackupPath"
Write-Output "RESTORE_HOST=$dbHost"
Write-Output "RESTORE_PORT=$dbPort"
Write-Output "RESTORE_OK=YES"
