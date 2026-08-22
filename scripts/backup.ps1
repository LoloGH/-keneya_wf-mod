<#
.SYNOPSIS
    Sauvegarde de la base de KEneYa WorkFlow (Windows, pile Docker).

.DESCRIPTION
        powershell -ExecutionPolicy Bypass -File .\scripts\backup.ps1 [-Destination C:\sauvegardes]

    Produit un fichier keneya_workflow-AAAAMMJJ-HHMMSS.sql.
    Voir le README pour la planification par tache planifiee Windows.

.NOTES
    Le dump est ecrit dans le conteneur puis recupere avec « docker compose cp ».
    On evite ainsi la redirection PowerShell, qui reencoderait le fichier (BOM
    UTF-8) et rendrait le .sql inexploitable a la restauration.
#>

param([string] $Destination = '.\backups')

$ErrorActionPreference = 'Stop'
Set-Location -Path (Join-Path $PSScriptRoot '..')

New-Item -ItemType Directory -Force -Path $Destination | Out-Null

$settings = @{
    DB_DATABASE = 'keneya_workflow'
    DB_USERNAME = 'keneya'
    DB_PASSWORD = ''
}

Get-Content '.env' | ForEach-Object {
    if ($_ -match '^\s*(DB_DATABASE|DB_USERNAME|DB_PASSWORD)\s*=\s*(.*)$') {
        $settings[$Matches[1]] = $Matches[2].Trim().Trim('"')
    }
}

$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$name = "$($settings.DB_DATABASE)-$stamp.sql"
$target = Join-Path $Destination $name

& docker compose exec -T db sh -c @"
mariadb-dump --user='$($settings.DB_USERNAME)' --password='$($settings.DB_PASSWORD)' \
    --single-transaction --routines '$($settings.DB_DATABASE)' > /tmp/$name
"@
if ($LASTEXITCODE -ne 0) { throw 'La sauvegarde a echoue.' }

& docker compose cp "db:/tmp/$name" $target
if ($LASTEXITCODE -ne 0) { throw 'La recuperation du fichier de sauvegarde a echoue.' }

& docker compose exec -T db rm -f "/tmp/$name"

Write-Host "Sauvegarde ecrite : $target"

# Conservation des 30 dernieres sauvegardes.
Get-ChildItem -Path $Destination -Filter '*.sql' |
    Sort-Object LastWriteTime -Descending |
    Select-Object -Skip 30 |
    Remove-Item -Force
