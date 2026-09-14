#!/usr/bin/env bash
#
# Mise a jour d'une installation en service, depuis GitHub.
#
#   ./scripts/update.sh [--sans-sauvegarde] [--build]
#
# Ecrit apres avoir fait la manoeuvre a la main, seize commandes durant, sur
# le serveur de l'hopital. Chaque etape ci-dessous est une de ces commandes,
# ou un piege rencontre ce jour-la.
#
# --sans-sauvegarde  saute la sauvegarde. A ne demander que si l'on vient d'en
#                    prendre une : c'est le seul retour en arriere possible.
# --build            reconstruit l'image Docker, necessaire quand `docker/` a
#                    bouge. Le script le signale de lui-meme si c'est le cas.
#
# ---------------------------------------------------------------------------
# Pourquoi un script plutot qu'une suite de commandes dans un README
# ---------------------------------------------------------------------------
#
# Il en reste sept ou huit, et chacune se paie cher quand on l'oublie : une
# sauvegarde qui doit preceder des migrations sans retour en arriere, un
# autoloader a regenerer sans quoi les classes nouvelles restent introuvables,
# des conteneurs a redemarrer dans un ordre precis, une verification qui doit
# attendre la page et non le conteneur.
#
# La plus couteuse a disparu avec la v3.4.2 : le module DME etait un second
# depot, et en tirer un sans l'autre ne produisait pas d'erreur franche —
# l'application demarrait, et se comportait mal a l'endroit precis ou un cote
# appelait ce qui n'existait pas encore de l'autre. Il fait desormais partie
# de ce depot.

set -euo pipefail

cd "$(dirname "$0")/.."

RACINE="$(pwd)"

SAUVEGARDE=1
BUILD=0

for argument in "$@"; do
    case "$argument" in
        --sans-sauvegarde) SAUVEGARDE=0 ;;
        --build) BUILD=1 ;;
        *) echo "Option inconnue : $argument" >&2; exit 1 ;;
    esac
done

titre()  { printf '\n\033[1m==> %s\033[0m\n' "$1"; }
info()   { printf '    %s\n' "$1"; }
erreur() { printf '\033[31mErreur :\033[0m %s\n' "$1" >&2; exit 1; }

compose() {
    if docker compose version >/dev/null 2>&1; then docker compose "$@"
    else docker-compose "$@"; fi
}

# Docker seulement si la pile de ce projet tourne reellement : Docker installe
# sur la machine ne veut pas dire que l'application y est deployee ainsi.
if command -v docker >/dev/null 2>&1 && compose ps --status running --services 2>/dev/null | grep -qx app; then
    DOCKER=1
    artisan() { compose exec -T app php artisan "$@"; }
else
    DOCKER=0
    command -v php >/dev/null 2>&1 || erreur "ni pile Docker demarree, ni PHP en ligne de commande : impossible de determiner comment cette installation tourne."
    artisan() { php artisan "$@"; }
fi

titre "Topologie"
info "$([ "$DOCKER" = 1 ] && echo 'pile Docker Compose' || echo 'installation native')"

# ------------------------------------------------------ Etat des deux depots

titre "Verification du depot"

[ -d "$RACINE/modules/dme" ] || erreur "le module DME est introuvable dans $RACINE/modules/dme. Depuis la v3.4.2 il fait partie de ce depot : verifiez que le clone est complet."

# `--untracked-files=no` : un fichier depose a cote, un journal, une
# sauvegarde, ne regarde pas la mise a jour. Ce qui la bloque, c'est un
# fichier suivi qui a ete modifie et que le `pull` ecraserait.
#
# On ne touche pas a `core.fileMode` : sur un serveur Linux il est deja juste,
# et le forcer sur un poste Windows ferait passer pour modifie tout fichier
# executable, NTFS ne portant pas ce bit.
if [ -n "$(git -C "$RACINE" status --porcelain --untracked-files=no)" ]; then
    git -C "$RACINE" status --short --untracked-files=no >&2
    erreur "$RACINE porte des modifications locales. Traitez-les avant de recommencer : le script ne les ecrase pas."
fi

info "aucune modification locale"

# --------------------------------------------------------------- Sauvegarde
#
# Avant le `pull`, et non apres les migrations : c'est le seul moment ou l'on
# a encore l'etat d'avant. Les migrations de ce projet ne se contentent pas de
# toucher au schema — certaines reecrivent des donnees et n'ont pas de retour
# en arriere. La sauvegarde *est* le retour en arriere.

if [ "$SAUVEGARDE" = 1 ]; then
    titre "Sauvegarde"
    DEST_SAUVEGARDE="${KENEYA_BACKUP_DIR:-$HOME/backup_maj_$(date +%F-%H%M)}"
    ./scripts/backup.sh "$DEST_SAUVEGARDE" \
        || erreur "la sauvegarde a echoue. La mise a jour s'arrete : sans elle, les migrations qui reecrivent des donnees seraient sans retour."
else
    titre "Sauvegarde"
    info "sautee sur demande (--sans-sauvegarde)."
fi

# ---------------------------------------------------------------- Recuperation

titre "Recuperation depuis GitHub"

AVANT_HOTE="$(git -C "$RACINE" rev-parse HEAD)"

git -C "$RACINE" pull --ff-only

APRES_HOTE="$(git -C "$RACINE" rev-parse HEAD)"

if [ "$AVANT_HOTE" = "$APRES_HOTE" ]; then
    info "le depot etait deja a jour."
else
    git -C "$RACINE" --no-pager log --oneline "$AVANT_HOTE..$APRES_HOTE" | sed 's/^/    /'
fi

# L'image ne se reconstruit pas toute seule, et rien ne signale qu'elle aurait
# du : une extension PHP ajoutee au Dockerfile manquerait a l'execution, avec
# une erreur qui ne parle pas du deploiement.
if [ "$DOCKER" = 1 ] && [ "$BUILD" = 0 ] && [ "$AVANT_HOTE" != "$APRES_HOTE" ] \
   && ! git -C "$RACINE" diff --quiet "$AVANT_HOTE" "$APRES_HOTE" -- docker/; then
    erreur "le dossier docker/ a change dans cette mise a jour : relancez avec --build."
fi

if [ "$BUILD" = 1 ] && [ "$DOCKER" = 1 ]; then
    titre "Reconstruction de l'image"
    compose build
    compose up -d
fi

# ------------------------------------------------------------- Dependances
#
# En root, et c'est voulu : `vendor/` appartient a root en lecture seule, le
# conteneur servant les requetes sous `www-data`. L'application ne peut donc
# pas reecrire ses propres dependances — bonne chose — et c'est l'etape de
# deploiement qui doit avoir les droits, pas l'application.
#
# `install` a chaque fois, et non `dump-autoload` quand rien ne semble avoir
# bouge. Cette optimisation a coute une panne.
#
# Le script decidait en comparant l'etat avant et apres **son propre**
# `git pull`. Un depot deja tire a la main lui faisait donc conclure que rien
# n'avait change ; il sautait l'installation, et le lien symbolique
# `vendor/keneya/dme` restait pointe sur l'ancien emplacement du module. Toute
# commande artisan echouait alors sur un fichier introuvable, le planificateur
# et le worker redemarraient en boucle, et l'application rendait 502.
#
# Le raisonnement etait faux a la racine : ce qui compte n'est pas ce que ce
# script vient de tirer, c'est ce que `vendor/` contient par rapport a
# `composer.lock`. `composer install` sait repondre a cette question, et
# repond vite quand la reponse est « rien a faire ». Une etape de deploiement
# n'a pas a etre maligne, elle a a etre juste deux fois sur deux.
#
# `--no-dev` : un serveur qui recoit des patients n'a besoin ni de PHPUnit ni
# des outils de developpement. Consequence a connaitre — la suite de tests ne
# s'y lance plus, et un `composer install` sans ce drapeau la ramene.

titre "Dependances"

composer_root() {
    if [ "$DOCKER" = 1 ]; then
        compose exec -T -u root -e COMPOSER_ALLOW_SUPERUSER=1 app composer "$@"
    else
        composer "$@"
    fi
}

composer_root install --no-interaction --no-dev --optimize-autoloader

# Le lien du module est refait par l'installation ci-dessus, mais un lien casse
# ne se voit qu'a la premiere requete, sous la forme d'un 502 qui ne nomme pas
# sa cause. On le verifie tant qu'on sait encore pourquoi.
if [ ! -e "$RACINE/vendor/keneya/dme/src/DmeServiceProvider.php" ]; then
    erreur "vendor/keneya/dme ne mene a rien apres l'installation. Le lien du depot \`path\` n'a pas ete refait : verifiez que modules/dme existe et relancez \`composer install\` a la main."
fi

titre "Migrations"
artisan migrate --force

# ----------------------------------------------------- Processus a redemarrer
#
# Aucune commande de cache ici : `docker/php/dev-entrypoint.sh` reconstruit
# config, routes et evenements a chaque demarrage du conteneur, et vide les
# gabarits. Les lancer aussi depuis ce script les ferait deux fois, dont une
# sous un compte qui n'a pas les droits d'ecriture sur `bootstrap/cache`.

titre "Redemarrage des processus"

if [ "$DOCKER" = 1 ]; then
    # `app` d'abord : l'image regle opcache.validate_timestamps = 0, PHP-FPM ne
    # relit donc jamais un fichier deja compile. Sans ce redemarrage,
    # l'application continue de servir l'ancien code par-dessus la base
    # migree.
    compose restart app scheduler queue-worker
    # `web` ensuite, et pas avant : Nginx resout l'adresse d'`app` une fois
    # pour toutes, et repond 502 tant qu'il pointe sur l'ancien conteneur.
    compose restart web
    info "app, scheduler, queue-worker, web"
else
    # Le worker garde en memoire le code charge a son demarrage : sans
    # redemarrage il continue d'envoyer les SMS avec l'ancienne mise en forme.
    if command -v systemctl >/dev/null 2>&1; then
        sudo systemctl restart keneya-queue keneya-scheduler
        sudo systemctl reload php8.4-fpm 2>/dev/null || sudo systemctl reload php-fpm
        info "keneya-queue, keneya-scheduler, php-fpm"
    else
        info "systemd absent : redemarrez a la main le worker de la file et PHP-FPM."
    fi
fi

# ------------------------------------------------------------- Verification
#
# Le point d'entree reconstruit trois caches avant de rendre la main : le
# conteneur repond `Up` bien avant d'etre pret. On attend donc la page, pas le
# conteneur.

titre "Verification"

PORT="$(grep -E '^APP_HTTP_PORT=' .env 2>/dev/null | cut -d= -f2 || true)"
PORT="${PORT:-8080}"
URL="http://localhost:${PORT}/connexion"

CODE=000
for _ in $(seq 1 60); do
    CODE="$(curl -so /dev/null -w '%{http_code}' "$URL" 2>/dev/null || echo 000)"
    [ "$CODE" = 200 ] && break
    sleep 2
done

[ "$CODE" = 200 ] || erreur "l'application repond $CODE sur $URL. Journaux : docker compose logs --tail=50 app"

info "l'application repond 200 sur $URL"

cat <<EOF

======================================================================
  Mise a jour terminee.

  $(git -C "$RACINE" rev-parse --short HEAD)  $(git -C "$RACINE" log -1 --format=%s)
$([ "$SAUVEGARDE" = 1 ] && printf '\n  Sauvegarde  %s\n' "$DEST_SAUVEGARDE")
  Ne lancez PAS scripts/verify-deploy.sh sur cette installation :
  il vide la base qu'il verifie. Il s'adresse a une pile jetable.
======================================================================
EOF
