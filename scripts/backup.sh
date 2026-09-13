#!/usr/bin/env bash
#
# Sauvegarde de KEneYa WorkFlow : base SQL, pieces jointes, signatures.
#
#   ./scripts/backup.sh [dossier-de-destination]
#
# Fonctionne sur les deux topologies de deploiement du projet :
#   - pile Docker Compose (service `db`) ;
#   - installation native (Apache + PHP-FPM + MariaDB de l'hote),
#     ou aucun conteneur `db` n'existe.
#
# Produit dans le dossier indique (par defaut ./backups) :
#   - keneya_workflow-AAAAMMJJ-HHMMSS.sql : la base ;
#   - attachments-AAAAMMJJ-HHMMSS.tar.gz  : les pieces jointes, qui vivent hors
#     de la base et sans lesquelles un dossier patient ne se restaure pas ;
#   - signatures-AAAAMMJJ-HHMMSS.tar.gz   : les signatures et tampons des
#     medecins, qui vivent egalement hors de la base et sans lesquels une
#     ordonnance se reimprime amputee de ce qui l'authentifie.
#
# Ces fichiers sont deposes par le serveur web : la sauvegarde doit donc
# tourner sous un compte capable de les lire (root, ou le compte du serveur
# web). Sinon le script s'arrete plutot que de produire une archive partielle.

set -euo pipefail

cd "$(dirname "$0")/.."

DEST="${1:-./backups}"
mkdir -p "$DEST"

# shellcheck disable=SC1091
set -a; [ -f .env ] && . ./.env; set +a

STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$DEST/${DB_DATABASE:-keneya_workflow}-$STAMP.sql"

DB_NAME="${DB_DATABASE:-keneya_workflow}"
DB_USER="${DB_USERNAME:-keneya}"

# Le mot de passe ne passe jamais en argument de commande : il serait alors
# visible dans `ps` par tout utilisateur de la machine. On le transmet par un
# fichier temporaire lisible du seul proprietaire, ou par l'environnement du
# conteneur.
if docker compose ps -q db 2>/dev/null | grep -q .; then
    docker compose exec -T -e MYSQL_PWD="${DB_PASSWORD:-}" db \
        mariadb-dump \
            --user="$DB_USER" \
            --single-transaction \
            --no-tablespaces \
            --routines \
            "$DB_NAME" > "$FILE"
else
    DUMP_BIN="$(command -v mariadb-dump || command -v mysqldump || true)"

    if [ -z "$DUMP_BIN" ]; then
        echo "Erreur : ni mariadb-dump ni mysqldump ne sont installes sur cette machine." >&2
        exit 1
    fi

    CNF="$(mktemp)"
    chmod 600 "$CNF"
    trap 'rm -f "$CNF"' EXIT
    printf '[client]\nuser=%s\npassword=%s\n' "$DB_USER" "${DB_PASSWORD:-}" > "$CNF"

    "$DUMP_BIN" \
        --defaults-extra-file="$CNF" \
        --host="${DB_HOST:-127.0.0.1}" \
        --port="${DB_PORT:-3306}" \
        --single-transaction \
        --no-tablespaces \
        --routines \
        "$DB_NAME" > "$FILE"
fi

# Un dump vide signale un echec silencieux : mieux vaut le dire tout de suite
# que de le decouvrir le jour d'une restauration.
if [ ! -s "$FILE" ]; then
    echo "Erreur : la sauvegarde $FILE est vide, la base n'a pas ete exportee." >&2
    rm -f "$FILE"
    exit 1
fi

chmod 600 "$FILE"

echo "Sauvegarde SQL ecrite : $FILE ($(du -h "$FILE" | cut -f1))"

# Deux dossiers vivent hors de la base, et un export SQL seul ne suffit donc pas
# a restaurer l'application :
#   - storage/app/attachments : les pieces jointes des dossiers patients ;
#   - storage/app/signatures  : les signatures et tampons apposes sur les
#     ordonnances (v3.2.9). Une ordonnance restauree sans eux est un document a
#     valeur legale ampute ; ce dossier est donc sauvegarde au meme titre que
#     les pieces jointes, et non quand on y pense.
# Sous Docker, ces dossiers ne sont pas sur l'hote.
#
# `docker-compose.yml` monte le volume nomme `keneya_storage` par-dessus tout
# `storage/`. L'hote garde donc un point de montage vide a `storage/app`, et
# les fichiers ne sont lisibles que depuis le conteneur.
#
# Cette fonction lisait l'hote sans condition. Le resultat n'etait pas une
# erreur mais une phrase rassurante — « Aucune donnee a sauvegarder » — et un
# code de sortie zero : toutes les sauvegardes prises sur une pile Docker ne
# contenaient que le SQL. Une restauration aurait rendu une base dont chaque
# piece jointe, chaque signature et chaque document medical designe un fichier
# qui n'a jamais ete sauvegarde. C'est le pire genre de panne de sauvegarde :
# elle ne se voit que le jour ou l'on en a besoin.
#
# Le dump SQL, lui, savait deja distinguer les deux topologies. Les fichiers
# suivent desormais la meme regle.
if docker compose ps -q app 2>/dev/null | grep -q .; then
    DANS_CONTENEUR=1
else
    DANS_CONTENEUR=0
fi

# Vrai si le dossier existe et contient quelque chose, la ou il vit reellement.
dossier_peuple() {
    if [ "$DANS_CONTENEUR" = 1 ]; then
        docker compose exec -T app sh -c "[ -d '$1' ] && [ -n \"\$(ls -A '$1' 2>/dev/null)\" ]" 2>/dev/null
    else
        [ -d "$1" ] && [ -n "$(ls -A "$1" 2>/dev/null)" ]
    fi
}

archiver_dossier() {
    dossier="$1"
    prefixe="$2"
    libelle="$3"

    if ! dossier_peuple "$dossier"; then
        echo "Aucune donnee a sauvegarder dans $dossier$([ "$DANS_CONTENEUR" = 1 ] && echo ' (vu depuis le conteneur)')."
        return 0
    fi

    archive="$DEST/$prefixe-$STAMP.tar.gz"

    # Les fichiers sont deposes par le serveur web : si l'utilisateur qui
    # sauvegarde ne peut pas les lire, tar n'archive qu'une partie du dossier
    # sans que rien ne l'indique. On refuse plutot que de produire une archive
    # trompeuse.
    if [ "$DANS_CONTENEUR" = 1 ]; then
        # `tar` ecrit sur la sortie standard et l'archive se forme sur l'hote :
        # rien n'est depose dans le conteneur, dont le systeme de fichiers est
        # jetable.
        ok=0
        docker compose exec -T app tar czf - -C "$dossier" . \
            > "$archive" 2>"$DEST/.tar-erreurs" || ok=1
    else
        ok=0
        tar czf "$archive" -C "$dossier" . 2>"$DEST/.tar-erreurs" || ok=1
    fi

    if [ "$ok" != 0 ]; then
        echo "Erreur : archivage des $libelle incomplet." >&2
        sed 's/^/    /' "$DEST/.tar-erreurs" >&2
        echo "    Lancez la sauvegarde avec un compte capable de lire $dossier (root, ou le compte du serveur web)." >&2
        rm -f "$archive" "$DEST/.tar-erreurs"
        exit 1
    fi

    # Une archive vide alors que le dossier ne l'est pas : meme raisonnement que
    # pour le dump SQL, mieux vaut le dire ici que le decouvrir a la
    # restauration.
    if [ ! -s "$archive" ]; then
        echo "Erreur : l'archive des $libelle est vide alors que $dossier contient des fichiers." >&2
        rm -f "$archive" "$DEST/.tar-erreurs"
        exit 1
    fi

    rm -f "$DEST/.tar-erreurs"
    chmod 600 "$archive"
    echo "Sauvegarde des $libelle ecrite : $archive ($(du -h "$archive" | cut -f1))"
}

archiver_dossier storage/app/attachments attachments "pieces jointes"
archiver_dossier storage/app/signatures signatures "signatures et tampons"

# Conservation des 30 dernieres sauvegardes de chaque type.
#
# Le tri se fait prefixe par prefixe, et non sur *.tar.gz en bloc : deux series
# melangees ne laisseraient que quinze exemplaires de chacune, et une serie
# produite a chaque execution finirait par evincer entierement une serie plus
# rare. Les pieces jointes disparaitraient alors des sauvegardes sans un mot.
#
# `|| true` sur chaque purge, et ce n'est pas de la negligence : sous
# `set -euo pipefail`, un motif qui ne correspond a rien fait sortir `ls` en
# erreur, la sortie du tube devient celle-la, et le script s'arretait donc en
# echec la premiere fois qu'on sauvegardait vers un dossier neuf — apres avoir
# pourtant tout ecrit correctement. Une sauvegarde reussie annoncee comme un
# echec apprend a ne plus lire le code de sortie, ce qui est exactement ce
# qu'il ne faut pas pour un script de sauvegarde.
purger() {
    ls -1t $1 2>/dev/null | tail -n +31 | xargs -r rm -- || true
}

purger "$DEST/*.sql"

for prefixe in attachments signatures; do
    purger "$DEST/$prefixe-*.tar.gz"
done

echo "Sauvegarde terminee dans $DEST."
