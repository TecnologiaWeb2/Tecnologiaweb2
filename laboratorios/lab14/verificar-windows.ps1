# verificar-windows.ps1 - Prepara todo y ejecuta php verificar.php EN WINDOWS (objetivo: 18 de 18).
# Uso (desde la carpeta lab14):   powershell -ExecutionPolicy Bypass -File .\verificar-windows.ps1
# Requiere: Docker Desktop, Node 18+, Google Chrome y PHP 8.3+ instalados en Windows.

Set-Location $PSScriptRoot
function Paso($t)  { Write-Host ""; Write-Host "== $t ==" -ForegroundColor Cyan }
function Bien($t)  { Write-Host "[OK] $t" -ForegroundColor Green }
function Falta($t) { Write-Host "[X]  $t" -ForegroundColor Red }
function Aviso($t) { Write-Host "[!]  $t" -ForegroundColor Yellow }

$todoBien = $true

Paso "1. Comprobando herramientas"

# Docker
if (Get-Command docker -ErrorAction SilentlyContinue) { Bien "Docker instalado" }
else { Falta "Falta Docker Desktop: https://www.docker.com/products/docker-desktop"; $todoBien = $false }

# Node 18+
if (Get-Command node -ErrorAction SilentlyContinue) {
    $mayor = [int]((node -v).TrimStart('v').Split('.')[0])
    if ($mayor -ge 18) { Bien "Node $(node -v)" }
    else { Falta "Node $(node -v) es muy viejo. Actualiza: winget install OpenJS.NodeJS.LTS"; $todoBien = $false }
} else { Falta "Falta Node. Instala: winget install OpenJS.NodeJS.LTS (y reabre PowerShell)"; $todoBien = $false }

# Chrome
$rutasChrome = @(
    "$env:ProgramFiles\Google\Chrome\Application\chrome.exe",
    "${env:ProgramFiles(x86)}\Google\Chrome\Application\chrome.exe",
    "$env:LocalAppData\Google\Chrome\Application\chrome.exe"
)
$chrome = $rutasChrome | Where-Object { Test-Path $_ } | Select-Object -First 1
if ($chrome) { Bien "Google Chrome encontrado"; $env:CHROME_RUTA = $chrome }
else { Falta "Falta Google Chrome: https://www.google.com/chrome"; $todoBien = $false }

# PHP + extensiones
if (Get-Command php -ErrorAction SilentlyContinue) {
    Bien "PHP $(php -r 'echo PHP_VERSION;')"
    $necesarias = @('curl', 'mbstring', 'openssl', 'pdo_mysql', 'fileinfo')
    $cargadas = (php -m) | ForEach-Object { $_.Trim().ToLower() }
    $faltan = $necesarias | Where-Object { $cargadas -notcontains $_ }
    if ($faltan) {
        Aviso "Activando extensiones de PHP: $($faltan -join ', ')"
        $dir = php -r "echo dirname(PHP_BINARY);"
        $ini = Join-Path $dir "php.ini"
        if (-not (Test-Path $ini)) {
            $base = Join-Path $dir "php.ini-development"
            if (Test-Path $base) { Copy-Item $base $ini }
        }
        if (Test-Path $ini) {
            $texto = Get-Content $ini
            $texto = $texto -replace '^;\s*extension_dir\s*=\s*"ext"', 'extension_dir = "ext"'
            foreach ($e in $faltan) { $texto = $texto -replace "^;\s*extension=$e\s*$", "extension=$e" }
            Set-Content $ini $texto
            $cargadas = (php -m) | ForEach-Object { $_.Trim().ToLower() }
            $faltan = $necesarias | Where-Object { $cargadas -notcontains $_ }
        }
        if ($faltan) { Falta "A PHP aun le faltan extensiones: $($faltan -join ', '). Edita php.ini en: $dir"; $todoBien = $false }
        else { Bien "Extensiones de PHP activadas" }
    } else { Bien "Extensiones de PHP correctas" }
} else {
    Falta "Falta PHP. Instala: winget install PHP.PHP.8.4 (y reabre PowerShell)"
    $todoBien = $false
}

if (-not $todoBien) {
    Write-Host ""
    Write-Host "Corrige lo marcado con [X], abre una PowerShell nueva y vuelve a ejecutar este script." -ForegroundColor Red
    exit 1
}

Paso "2. Dependencias de Node (playwright-core)"
if (-not (Test-Path "node_modules\playwright-core")) { npm install }
else { Bien "node_modules ya esta instalado" }

Paso "3. Iniciando MySQL y el contenedor PHP"
docker compose up -d
Write-Host "Esperando a que MySQL este listo..."
for ($i = 0; $i -lt 40; $i++) {
    $estado = docker inspect -f "{{.State.Health.Status}}" apicultor14-mysql 2>$null
    if ($estado -eq "healthy") { break }
    Start-Sleep -Seconds 3
}
if ($estado -ne "healthy") { Falta "MySQL no quedo listo. Revisa: docker compose logs mysql"; exit 1 }
Bien "MySQL listo en 127.0.0.1:3307"

Paso "4. Ejecutando verificar.php (en Windows, con tu Node y tu Chrome)"
php verificar.php
exit $LASTEXITCODE
