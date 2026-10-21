#Requires -Version 5.1
<#
.SYNOPSIS
  Create local PostgreSQL database rotor_testing and grant privileges to the app role.
.NOTES
  Requires a local admin role with CREATEDB (PG_ADMIN_USERNAME / PG_ADMIN_PASSWORD).
  Never prints passwords. Never grants CREATEDB to the application role.
#>
[CmdletBinding()]
param(
    [string]$EnvFile,
    [string]$PgBin = 'C:\Program Files\PostgreSQL\16\bin',
    [string]$TargetDatabase = 'rotor_testing',
    [string]$AdminUsername
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

if ($TargetDatabase -ne 'rotor_testing') {
    throw "Aborting: target must be rotor_testing."
}

$envMap = Get-DotEnvMap $EnvFile
$dbHost = if ($envMap['DB_HOST']) { $envMap['DB_HOST'] } else { '127.0.0.1' }
$dbPort = if ($envMap['DB_PORT']) { $envMap['DB_PORT'] } else { '5432' }
$appUser = if ($envMap['DB_USERNAME']) { $envMap['DB_USERNAME'] } else { 'rotor' }

$localHosts = @('127.0.0.1', 'localhost', '::1')
if ($localHosts -notcontains $dbHost) {
    throw "Refusing: DB_HOST='$dbHost' is not local."
}

if (-not $AdminUsername) {
    $AdminUsername = [string]$env:PG_ADMIN_USERNAME
}
if (-not $AdminUsername) {
    throw "Set PG_ADMIN_USERNAME (and PG_ADMIN_PASSWORD) to a local PostgreSQL role with CREATEDB. The application role cannot create databases."
}

$psql = Join-Path $PgBin 'psql.exe'
$previousPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = [string]$env:PG_ADMIN_PASSWORD

    $exists = ([string](& $psql -h $dbHost -p $dbPort -U $AdminUsername -d postgres -Atc "SELECT COUNT(*) FROM pg_database WHERE datname = 'rotor_testing'" 2>&1 | Out-String)).Trim()
    if ($exists -eq '1') {
        Write-Output "DATABASE_EXISTS=rotor_testing"
    } else {
        & $psql -h $dbHost -p $dbPort -U $AdminUsername -d postgres -c "CREATE DATABASE rotor_testing OWNER `"$appUser`";"
        if ($LASTEXITCODE -ne 0) { throw "CREATE DATABASE rotor_testing failed." }
        Write-Output "CREATED_DATABASE=rotor_testing"
    }

    & $psql -h $dbHost -p $dbPort -U $AdminUsername -d rotor_testing -c "GRANT CONNECT ON DATABASE rotor_testing TO `"$appUser`"; GRANT ALL ON SCHEMA public TO `"$appUser`";"
    if ($LASTEXITCODE -ne 0) { throw "GRANT on rotor_testing failed." }
    Write-Output "GRANTED_APP_ROLE=$appUser"
    Write-Output "CREATEDB_NOT_GRANTED_TO_APP_ROLE=YES"
} finally {
    if ($null -eq $previousPassword) {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    } else {
        $env:PGPASSWORD = $previousPassword
    }
}
