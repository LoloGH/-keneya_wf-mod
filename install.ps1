<#
.SYNOPSIS
    Installation de KEneYa WorkFlow sous Windows, via Docker Desktop.

.DESCRIPTION
    Equivalent Windows de install.sh. A lancer depuis PowerShell, dans le
    dossier du projet :

        powershell -ExecutionPolicy Bypass -File .\install.ps1

    Le script est idempotent : il peut etre relance sans risque.
#>

$ErrorActionPreference = 'Stop'
Set-Location -Path $PSScriptRoot

function Invoke-Compose {
    # Le parametre ne peut pas s'appeler $Args : c'est une variable automatique
    # de PowerShell.
    param([Parameter(ValueFromRemainingArguments = $true)] [string[]] $ComposeArgs)

    & docker compose @ComposeArgs
    if ($LASTEXITCODE -ne 0) {
        throw "La commande « docker compose $($ComposeArgs -join ' ') » a echoue."
    }
}

Write-Host '==> Verification de Docker Desktop'
if (-not (Get-Command docker -ErrorAction SilentlyContinue)) {
    throw "Docker Desktop n'est pas installe. Voir la section « Installation sans Docker » du README."
}

Write-Host '==> Preparation du fichier .env'
if (-not (Test-Path '.env')) {
    Copy-Item '.env.example' '.env'
    Write-Host '    .env cree a partir de .env.example — pensez a y changer les mots de passe.'
} else {
    Write-Host '    .env existe deja, il est conserve.'
}

Write-Host '==> Construction des conteneurs'
Invoke-Compose build

Write-Host '==> Demarrage de la pile (app, web, db)'
Invoke-Compose up -d

Write-Host '==> Attente de la base de donnees'
for ($i = 0; $i -lt 60; $i++) {
    & docker compose exec -T app php -r 'exit(0);' *> $null
    if ($LASTEXITCODE -eq 0) { break }
    Start-Sleep -Seconds 2
}

Write-Host '==> Generation de la cle applicative'
if ((Get-Content '.env') -match '^APP_KEY=.+$') {
    Write-Host '    APP_KEY deja definie, elle est conservee.'
} else {
    Invoke-Compose exec -T app php artisan key:generate --force
}

Write-Host '==> Migrations et donnees initiales'
Invoke-Compose exec -T app php artisan migrate --seed --force

Write-Host '==> Mise en cache de la configuration'
Invoke-Compose exec -T app php artisan config:cache
Invoke-Compose exec -T app php artisan route:cache
Invoke-Compose exec -T app php artisan view:cache

$port = '8080'
$line = Select-String -Path '.env' -Pattern '^APP_HTTP_PORT=(.+)$' | Select-Object -First 1
if ($line) { $port = $line.Matches[0].Groups[1].Value.Trim() }

Write-Host ''
Write-Host '======================================================================'
Write-Host '  KEneYa WorkFlow est installe.'
Write-Host ''
Write-Host "  Application      : http://localhost:$port"
Write-Host "  Salle d'attente  : http://localhost:$port/board"
Write-Host ''
Write-Host '  Comptes de demonstration (mot de passe : valeur de SEED_DEFAULT_PASSWORD) :'
Write-Host '    admin@keneya.local        -> /admin'
Write-Host '    accueil@keneya.local      -> /reception'
Write-Host '    medecine@keneya.local     -> /service'
Write-Host ''
Write-Host '  Changez ces mots de passe avant toute mise en service reelle.'
Write-Host '======================================================================'
