# Baut die Desktop-App fuer Windows (x64): Installer *-setup.exe (NSIS,
# Installation pro Benutzer, ohne Adminrechte).
#
#   powershell -ExecutionPolicy Bypass -File desktop\bauen.ps1
#   powershell -ExecutionPolicy Bypass -File desktop\bauen.ps1 -NurApp
#
# Voraussetzungen auf dem BAU-Rechner (nicht bei den Benutzern):
#   - Git                 https://git-scm.com
#   - Node.js (LTS)       https://nodejs.org
#   - Rust                https://rustup.rs  (verlangt die Visual Studio Build
#                         Tools mit "Desktopentwicklung mit C++")
# PHP und Composer sind NICHT noetig: Das Skript nimmt das PHP aus der
# FrankenPHP-Laufzeit, die ohnehin in die App kommt.
#
# Bewusst ohne Umlaute: Windows PowerShell 5.1 liest Skripte ohne BOM als ANSI.

#Requires -Version 5.1
param([switch]$NurApp)

$ErrorActionPreference = 'Stop'
$ProgressPreference = 'SilentlyContinue'   # Invoke-WebRequest ist sonst sehr langsam

$Desktop = $PSScriptRoot
$Root = Split-Path $Desktop -Parent
$Build = Join-Path $Desktop 'build'
$FrankenVersion = if ($env:FRANKENPHP_VERSION) { $env:FRANKENPHP_VERSION } else { 'v1.13.5' }
$Cache = Join-Path $Desktop ".cache\$FrankenVersion"
$App = Join-Path $Build 'app'
$Lz = Join-Path $Build 'laufzeit'

function Schritt([string]$Text) { Write-Host "`n== $Text" -ForegroundColor Cyan }

function Pruefe([string]$Name, [string]$Hinweis) {
    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "$Name fehlt. Installieren: $Hinweis"
    }
}

function Laufe([string]$Programm, [string[]]$Argumente) {
    & $Programm @Argumente
    if ($LASTEXITCODE -ne 0) {
        throw "Fehlgeschlagen (Exit $LASTEXITCODE): $Programm $($Argumente -join ' ')"
    }
}

function Lade([string]$Url, [string]$Ziel) {
    if (-not (Test-Path $Ziel)) {
        Write-Host "   lade $(Split-Path $Ziel -Leaf) ..."
        Invoke-WebRequest -Uri $Url -OutFile "$Ziel.part" -UseBasicParsing
        Move-Item "$Ziel.part" $Ziel -Force
    }
}

Schritt 'Voraussetzungen'
Pruefe 'git' 'https://git-scm.com'
Pruefe 'npm' 'https://nodejs.org (LTS)'
if (-not $NurApp) {
    Pruefe 'cargo' 'https://rustup.rs (dazu Visual Studio Build Tools mit "Desktopentwicklung mit C++")'
}
# NODE_ENV=production liesse npm die devDependencies (Vite, Tauri-CLI) weg.
$env:NODE_ENV = 'development'

New-Item -ItemType Directory -Force -Path $Cache, $Build | Out-Null
foreach ($Ordner in $App, $Lz) {
    if (Test-Path $Ordner) { Remove-Item $Ordner -Recurse -Force }
    New-Item -ItemType Directory -Path $Ordner | Out-Null
}

# ---------------------------------------------------------------------------
Schritt "PHP-Laufzeit (FrankenPHP $FrankenVersion)"
$Zip = Join-Path $Cache 'frankenphp-windows-x86_64.zip'
Lade "https://github.com/php/frankenphp/releases/download/$FrankenVersion/frankenphp-windows-x86_64.zip" $Zip
Expand-Archive -Path $Zip -DestinationPath $Lz -Force

# Entwicklerbeigaben des PHP-Pakets weglassen.
foreach ($Weg in 'dev', 'extras', 'phpdbg.exe', 'php8phpdbg.dll', 'php-cgi.exe', 'php8apache2_4.dll', 'deplister.exe') {
    Remove-Item (Join-Path $Lz $Weg) -Recurse -Force -ErrorAction SilentlyContinue
}
Get-ChildItem $Lz -Filter '*.lib' | Remove-Item -Force

Copy-Item (Join-Path $Desktop 'laufzeit\php.ini') $Lz
Copy-Item (Join-Path $Desktop 'laufzeit\Caddyfile') $Lz
Set-Content -Path (Join-Path $Lz 'VERSION') -Value $FrankenVersion -Encoding Ascii

foreach ($Datei in 'frankenphp.exe', 'php.exe', 'php8ts.dll', 'ext\php_pdo_sqlite.dll') {
    if (-not (Test-Path (Join-Path $Lz $Datei))) { throw "Laufzeit unvollstaendig: $Datei fehlt" }
}

# ---------------------------------------------------------------------------
# PHP und FrankenPHP brauchen vcruntime140(_1).dll und msvcp140.dll. Auf vielen
# Schulrechnern fehlt das "Visual C++ Redistributable", und ohne Adminrechte
# laesst es sich nicht nachinstallieren. Microsoft erlaubt, die DLLs neben das
# Programm zu legen (app-local). Quelle: die Build Tools dieses Rechners.
Schritt 'Visual-C++-Laufzeit (app-local)'
$Crt = $null
$VsWhere = Join-Path ${env:ProgramFiles(x86)} 'Microsoft Visual Studio\Installer\vswhere.exe'
if (Test-Path $VsWhere) {
    $Vs = & $VsWhere -latest -products * -property installationPath
    if ($Vs) {
        $Crt = Get-ChildItem -Path (Join-Path $Vs 'VC\Redist\MSVC\*\x64') -Directory -Filter 'Microsoft.VC14*.CRT' -ErrorAction SilentlyContinue |
            Sort-Object FullName -Descending | Select-Object -First 1
    }
}
foreach ($Dll in 'vcruntime140.dll', 'vcruntime140_1.dll', 'msvcp140.dll') {
    $Quelle = if ($Crt) { Join-Path $Crt.FullName $Dll } else { Join-Path $env:SystemRoot "System32\$Dll" }
    if (-not (Test-Path $Quelle)) { throw "$Dll nicht gefunden ($Quelle)" }
    Copy-Item $Quelle $Lz -Force
}
Write-Host "   Quelle: $(if ($Crt) { $Crt.FullName } else { 'System32' })"

# Ab hier laeuft PHP aus der Laufzeit (Composer, tcpdf-Schriften).
$env:PHPRC = $Lz
$Php = Join-Path $Lz 'php.exe'
Laufe $Php @('-r', 'exit(extension_loaded("pdo_sqlite") && extension_loaded("openssl") ? 0 : 1);')

# ---------------------------------------------------------------------------
Schritt 'Anwendung zusammenstellen'
$OhneOrdner = '.git', '.github', '.gitea', 'desktop', 'docker', 'tests', 'docs', 'node_modules',
    'vendor', 'storage', 'backups', 'dist', 'public\build', 'public\hot' | ForEach-Object { Join-Path $Root $_ }
$OhneDateien = '*.sqlite', '*.sqlite-*', '*.sql', '.env', '.env.*', 'docker-compose*.yml', 'deploy.sh',
    'install.sh', 'backup.sh', 'Makefile', 'phpunit.xml', 'phpstan*'
& robocopy $Root $App /E /NFL /NDL /NJH /NJS /NP /XD @OhneOrdner /XF @OhneDateien | Out-Null
if ($LASTEXITCODE -ge 8) { throw "robocopy fehlgeschlagen (Exit $LASTEXITCODE)" }
$global:LASTEXITCODE = 0

# Nur die Markdown-Dateien im Hauptordner weglassen (die Hilfekapitel bleiben).
Get-ChildItem $App -Filter '*.md' -File | Remove-Item -Force
# Das Basisschema ist eine .sql-Datei und gehoert dazu.
New-Item -ItemType Directory -Force -Path (Join-Path $App 'database\schema') | Out-Null
Copy-Item (Join-Path $Root 'database\schema\basis.sql') (Join-Path $App 'database\schema\basis.sql')
New-Item -ItemType Directory -Force -Path (Join-Path $App 'storage') | Out-Null
Get-ChildItem (Join-Path $App 'bootstrap\cache') -Filter '*.php' -ErrorAction SilentlyContinue | Remove-Item -Force

$Composer = Join-Path $Cache 'composer.phar'
Lade 'https://getcomposer.org/download/latest-stable/composer.phar' $Composer
$Summe = Join-Path $Cache 'composer.phar.sha256'
Invoke-WebRequest 'https://getcomposer.org/download/latest-stable/composer.phar.sha256' -OutFile $Summe -UseBasicParsing
$Erwartet = (Get-Content $Summe -Raw).Trim().Split(' ')[0]
$Tatsaechlich = (Get-FileHash $Composer -Algorithm SHA256).Hash.ToLower()
if ($Erwartet -and $Tatsaechlich -ne $Erwartet.ToLower()) {
    Remove-Item $Composer -Force
    throw 'Pruefsumme von composer.phar stimmt nicht - bitte erneut starten.'
}

Push-Location $App
try {
    Laufe $Php @($Composer, 'install', '--no-dev', '--optimize-autoloader', '--no-interaction', '--no-scripts', '--no-progress')
} finally { Pop-Location }

# tcpdf 7 bringt keine fertigen Schriften mit (vgl. docker/build-tcpdf-fonts.sh).
$FontPkg = Join-Path $App 'vendor\tecnickcom\tc-lib-pdf-font'
$FontZiel = Join-Path $FontPkg 'target\fonts'
if ((Test-Path (Join-Path $FontPkg 'util')) -and -not (Test-Path (Join-Path $FontZiel 'helvetica.json'))) {
    Write-Host '   erzeuge tcpdf-Schriften ...'
    Push-Location (Join-Path $FontPkg 'util')
    try {
        Laufe $Php @($Composer, 'install', '--no-interaction', '--quiet')
        New-Item -ItemType Directory -Force -Path (Join-Path $FontPkg 'vendor') | Out-Null
        Set-Content -Path (Join-Path $FontPkg 'vendor\autoload.php') -Encoding Ascii `
            -Value '<?php require __DIR__."/../../../autoload.php";'
        Laufe $Php @('bulk_convert.php')
    } finally { Pop-Location }
    Get-ChildItem $FontZiel -Recurse -File -Include '*.json', '*.z' |
        Where-Object { $_.DirectoryName -ne $FontZiel } |
        ForEach-Object { Copy-Item $_.FullName $FontZiel -Force }
}

Schritt 'Oberflaeche bauen'
Push-Location $Root
try {
    Laufe 'npm' @('ci', '--include=dev', '--no-audit', '--no-fund')
    Laufe 'npm' @('run', 'build')
} finally { Pop-Location }
Copy-Item (Join-Path $Root 'public\build') (Join-Path $App 'public\build') -Recurse -Force

$Stand = (& git -C $Root describe --tags --always --dirty).Trim()
Set-Content -Path (Join-Path $App 'VERSION') -Value $Stand -Encoding Ascii

$Groesse = { param($Pfad) '{0:N0} MB' -f ((Get-ChildItem $Pfad -Recurse -File | Measure-Object Length -Sum).Sum / 1MB) }
Write-Host "   app:      $(& $Groesse $App)"
Write-Host "   laufzeit: $(& $Groesse $Lz)"

# Rauchtest: Laeuft Laravel mit dieser Laufzeit? Mit eigenem, leeren Storage
# (wie die App ihn im Datenordner anlegt) - sonst "Please provide a valid
# cache path", weil build\app\storage absichtlich leer ist.
$Rauch = Join-Path $env:TEMP 'abiplaner-rauchtest'
foreach ($Unter in 'framework\views', 'framework\cache', 'framework\sessions', 'logs') {
    New-Item -ItemType Directory -Force -Path (Join-Path $Rauch $Unter) | Out-Null
}
$env:LARAVEL_STORAGE_PATH = $Rauch
Push-Location $App
try { Laufe $Php @('artisan', '--version') } finally {
    Pop-Location
    Remove-Item Env:\LARAVEL_STORAGE_PATH
    Remove-Item $Rauch -Recurse -Force -ErrorAction SilentlyContinue
}

if ($NurApp) { exit 0 }

# ---------------------------------------------------------------------------
Schritt 'Desktop-App bauen (NSIS)'
Push-Location $Desktop
try {
    if (-not (Test-Path 'node_modules\@tauri-apps')) {
        Laufe 'npm' @('install', '--include=dev', '--no-audit', '--no-fund')
    }
    # Update-Signatur (.sig neben dem Installer) nur mit privatem Update-Schluessel
    # (TAURI_SIGNING_PRIVATE_KEY, in GitHub Actions aus den Secrets).
    $TauriArgs = @('tauri', 'build', '--bundles', 'nsis')
    if (-not $env:TAURI_SIGNING_PRIVATE_KEY) {
        Write-Host '   Hinweis: kein Update-Schluessel - Installer ohne Update-Signatur.' -ForegroundColor Yellow
        $Json = Join-Path $env:TEMP 'abiplaner-ohne-updater.json'
        Set-Content -Path $Json -Value '{"bundle":{"createUpdaterArtifacts":false}}' -Encoding Ascii
        $TauriArgs += @('--config', $Json)
    }
    Laufe 'npx' $TauriArgs
} finally { Pop-Location }

Get-ChildItem (Join-Path $Desktop 'src-tauri\target\release\bundle\nsis') -Filter '*-setup.exe*' |
    ForEach-Object { Write-Host "`nErgebnis: $($_.FullName)  ($('{0:N0} MB' -f ($_.Length / 1MB)))" -ForegroundColor Green }
