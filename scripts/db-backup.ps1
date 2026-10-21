#Requires -Version 5.1
<#
.SYNOPSIS
  PostgreSQL custom-format dump of a local database (default: rotor).
.NOTES
  Never prints passwords. Fails if dump is missing or 0 bytes.
#>
[CmdletBinding()]
param(
    [string]$DestinationDirectory = 'C:\Temp\rotor-backups',
    [string]$EnvFile,
    [string]$PgBin = 'C:\Program Files\PostgreSQL\16\bin',
    [string]$Database
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

$envMap = Get-DotEnvMap $EnvFile
$dbHost = if ($envMap['DB_HOST']) { $envMap['DB_HOST'] } else { '127.0.0.1' }
$dbPort = if ($envMap['DB_PORT']) { $envMap['DB_PORT'] } else { '5432' }
$dbName = if ($Database) { $Database } elseif ($envMap['DB_DATABASE']) { $envMap['DB_DATABASE'] } else { 'rotor' }
$dbUser = if ($envMap['DB_USERNAME']) { $envMap['DB_USERNAME'] } else { 'rotor' }

$localHosts = @('127.0.0.1', 'localhost', '::1')
if ($localHosts -notcontains $dbHost) {
    throw "Refusing backup: DB_HOST='$dbHost' is not local."
}

$pgDump = Join-Path $PgBin 'pg_dump.exe'
if (-not (Test-Path -LiteralPath $pgDump)) {
    throw "pg_dump not found: $pgDump"
}

New-Item -ItemType Directory -Force -Path $DestinationDirectory | Out-Null
$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$outFile = Join-Path $DestinationDirectory "rotor_mvp0_${stamp}.dump"

$previousPassword = $env:PGPASSWORD
try {
    $env:PGPASSWORD = [string]$envMap['DB_PASSWORD']
    & $pgDump -h $dbHost -p $dbPort -U $dbUser -d $dbName -F c -f $outFile
    if ($LASTEXITCODE -ne 0) {
        throw "pg_dump failed with exit code $LASTEXITCODE"
    }
} finally {
    if ($null -eq $previousPassword) {
        Remove-Item Env:PGPASSWORD -ErrorAction SilentlyContinue
    } else {
        $env:PGPASSWORD = $previousPassword
    }
}

if (-not (Test-Path -LiteralPath $outFile)) {
    throw "Dump file was not created: $outFile"
}
$size = (Get-Item -LiteralPath $outFile).Length
if ($size -le 0) {
    throw "Dump file is empty: $outFile"
}

$hash = (Get-FileHash -LiteralPath $outFile -Algorithm SHA256).Hash
Write-Output "BACKUP_PATH=$outFile"
Write-Output "BACKUP_SIZE_BYTES=$size"
Write-Output "BACKUP_SHA256=$hash"
Write-Output "BACKUP_DATABASE=$dbName"
Write-Output "BACKUP_HOST=$dbHost"
