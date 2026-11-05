#Requires -Version 7
<#
.SYNOPSIS
  Arranque local medible de Proyecto Rotor (demo).
.DESCRIPTION
  Tiempo de puesta en marcha local = desde este script en un equipo ya preparado
  (PHP/Composer/Node/PostgreSQL + vendor/node_modules presentes) hasta Laravel+Next respondiendo.
  NO ejecuta migrate:fresh ni altera datos de negocio.
#>
$ErrorActionPreference = "Stop"
$root = Split-Path -Parent $PSScriptRoot
$backend = Join-Path $root "backend"
$frontend = Join-Path $root "frontend"

function Get-MonoMs {
  return [System.Diagnostics.Stopwatch]::GetTimestamp() * 1000.0 / [System.Diagnostics.Stopwatch]::Frequency
}

$t0 = Get-MonoMs
Write-Host "DEMO_START root=$root"

# PostgreSQL
$pgStart = Get-MonoMs
$tcp = Test-NetConnection 127.0.0.1 -Port 5432 -WarningAction SilentlyContinue
if (-not $tcp.TcpTestSucceeded) {
  throw "PostgreSQL no escucha en 127.0.0.1:5432"
}
$POSTGRESQL_MS = [int]((Get-MonoMs) - $pgStart)

# Confirm DB=rotor without printing password
Push-Location $backend
try {
  $dbName = php artisan tinker --execute="echo DB::selectOne('select current_database() as d')->d;"
  if (($dbName | Out-String).Trim() -ne "rotor") {
    throw "DB_DATABASE esperado=rotor"
  }
} finally {
  Pop-Location
}

# Laravel :8000
$laravelStart = Get-MonoMs
$existing8000 = Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue
if (-not $existing8000) {
  Start-Process -FilePath "php" -ArgumentList @("artisan", "serve", "--host=127.0.0.1", "--port=8000") -WorkingDirectory $backend -WindowStyle Minimized
}
$deadline = (Get-Date).AddSeconds(45)
do {
  try {
    $up = Invoke-WebRequest -Uri "http://127.0.0.1:8000/up" -UseBasicParsing -TimeoutSec 2
    if ($up.StatusCode -eq 200) { break }
  } catch {
    Start-Sleep -Milliseconds 400
  }
} while ((Get-Date) -lt $deadline)
if (-not $up -or $up.StatusCode -ne 200) {
  throw "Laravel /up no respondió a tiempo"
}
$LARAVEL_READY_MS = [int]((Get-MonoMs) - $laravelStart)

# Next :3000
$nextStart = Get-MonoMs
$existing3000 = Get-NetTCPConnection -LocalPort 3000 -State Listen -ErrorAction SilentlyContinue
if (-not $existing3000) {
  Start-Process -FilePath "npm" -ArgumentList @("run", "dev") -WorkingDirectory $frontend -WindowStyle Minimized
}
$deadline = (Get-Date).AddSeconds(90)
$nextOk = $false
do {
  try {
    $home = Invoke-WebRequest -Uri "http://127.0.0.1:3000/" -UseBasicParsing -TimeoutSec 3
    if ($home.StatusCode -ge 200 -and $home.StatusCode -lt 400) {
      $nextOk = $true
      break
    }
  } catch {
    Start-Sleep -Milliseconds 500
  }
} while ((Get-Date) -lt $deadline)
if (-not $nextOk) {
  throw "Next.js / no respondió a tiempo"
}
$NEXT_READY_MS = [int]((Get-MonoMs) - $nextStart)

$TOTAL_READY_MS = [int]((Get-MonoMs) - $t0)
$TOTAL_READY_SECONDS = [math]::Round($TOTAL_READY_MS / 1000.0, 2)

Write-Host "DEMO_READY=YES"
Write-Host "POSTGRESQL_MS=$POSTGRESQL_MS"
Write-Host "LARAVEL_READY_MS=$LARAVEL_READY_MS"
Write-Host "NEXT_READY_MS=$NEXT_READY_MS"
Write-Host "TOTAL_READY_MS=$TOTAL_READY_MS"
Write-Host "TOTAL_READY_SECONDS=$TOTAL_READY_SECONDS"
