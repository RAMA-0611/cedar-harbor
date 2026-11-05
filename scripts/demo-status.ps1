#Requires -Version 7
Write-Host "=== Rotor demo status ==="
$pg = Test-NetConnection 127.0.0.1 -Port 5432 -WarningAction SilentlyContinue
Write-Host "POSTGRESQL=$(if ($pg.TcpTestSucceeded) { 'UP' } else { 'DOWN' })"
foreach ($port in 8000, 3000) {
  $c = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
  Write-Host "PORT_${port}=$(if ($c) { 'LISTEN' } else { 'FREE' })"
}
try {
  $up = Invoke-WebRequest -Uri http://127.0.0.1:8000/up -UseBasicParsing -TimeoutSec 3
  Write-Host "LARAVEL_UP=$($up.StatusCode)"
} catch {
  Write-Host "LARAVEL_UP=DOWN"
}
try {
  $home = Invoke-WebRequest -Uri http://127.0.0.1:3000/ -UseBasicParsing -TimeoutSec 3
  Write-Host "NEXT_HOME=$($home.StatusCode)"
} catch {
  Write-Host "NEXT_HOME=DOWN"
}
