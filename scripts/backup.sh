#!/usr/bin/env bash
#
# Sauvegarde de la base de KEneYa WorkFlow (Linux, pile Docker).
#
#   ./scripts/backup.sh [dossier-de-destination]
#
# Produit un fichier keneya_workflow-AAAAMMJJ-HHMMSS.sql dans le dossier
# indique (par defaut ./backups). Voir README pour la planification par cron.

set -euo pipefail

cd "$(dirname "$0")/.."

DEST="${1:-./backups}"
mkdir -p "$DEST"

# shellcheck disable=SC1091
set -a; [ -f .env ] && . ./.env; set +a

STAMP="$(date +%Y%m%d-%H%M%S)"
FILE="$DEST/${DB_DATABASE:-keneya_workflow}-$STAMP.sql"

docker compose exec -T db \
    mariadb-dump \
        --user="${DB_USERNAME:-keneya}" \
        --password="${DB_PASSWORD}" \
        --single-transaction \
        --routines \
        "${DB_DATABASE:-keneya_workflow}" > "$FILE"

echo "Sauvegarde ecrite : $FILE"

# Conservation des 30 dernieres sauvegardes.
ls -1t "$DEST"/*.sql 2>/dev/null | tail -n +31 | xargs -r rm --
