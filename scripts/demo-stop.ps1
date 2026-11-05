#Requires -Version 7
$ErrorActionPreference = "Continue"
Write-Host "Stopping listeners on 8000/3000 for cedar-harbor demo (best-effort)..."

function Stop-Port([int]$Port) {
  $conns = Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue
  foreach ($c in $conns) {
    $proc = Get-CimInstance Win32_Process -Filter "ProcessId=$($c.OwningProcess)" -ErrorAction SilentlyContinue
    if ($null -eq $proc) { continue }
    $cmd = [string]$proc.CommandLine
    if ($cmd -match 'cedar-harbor' -or $cmd -match 'artisan serve' -or $cmd -match 'next') {
      Write-Host "Stopping PID $($c.OwningProcess) on :$Port"
      Stop-Process -Id $c.OwningProcess -Force -ErrorAction SilentlyContinue
    } else {
      Write-Host "SKIP PID $($c.OwningProcess) on :$Port (not identified as Rotor demo)"
    }
  }
}

Stop-Port 8000
Stop-Port 3000
Write-Host "DONE"
