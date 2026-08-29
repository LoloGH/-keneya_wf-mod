#!/usr/bin/env bash
#
# Sauvegarde de KEneYa WorkFlow : base SQL et pieces jointes.
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
#     de la base et sans lesquelles un dossier patient ne se restaure pas.
#
# Les pieces jointes sont deposees par le serveur web : la sauvegarde doit donc
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

# Les pieces jointes vivent hors de la base (storage/app/attachments) : un
# export SQL seul ne permettrait pas de restaurer un dossier patient complet.
PIECES="storage/app/attachments"

if [ -d "$PIECES" ] && [ -n "$(ls -A "$PIECES" 2>/dev/null)" ]; then
    ARCHIVE="$DEST/attachments-$STAMP.tar.gz"

    # Les fichiers sont deposes par le serveur web : si l'utilisateur qui
    # sauvegarde ne peut pas les lire, tar n'archive qu'une partie du dossier
    # sans que rien ne l'indique. On refuse plutot que de produire une archive
    # trompeuse.
    if ! tar czf "$ARCHIVE" -C "$PIECES" . 2>"$DEST/.tar-erreurs"; then
        echo "Erreur : archivage des pieces jointes incomplet." >&2
        sed 's/^/    /' "$DEST/.tar-erreurs" >&2
        echo "    Lancez la sauvegarde avec un compte capable de lire $PIECES (root, ou le compte du serveur web)." >&2
        rm -f "$ARCHIVE" "$DEST/.tar-erreurs"
        exit 1
    fi

    rm -f "$DEST/.tar-erreurs"
    chmod 600 "$ARCHIVE"
    echo "Sauvegarde des pieces jointes ecrite : $ARCHIVE ($(du -h "$ARCHIVE" | cut -f1))"
else
    echo "Aucune piece jointe a sauvegarder."
fi

# Conservation des 30 dernieres sauvegardes de chaque type.
ls -1t "$DEST"/*.sql 2>/dev/null | tail -n +31 | xargs -r rm --
ls -1t "$DEST"/*.tar.gz 2>/dev/null | tail -n +31 | xargs -r rm --
