#!/usr/bin/env bash
#
# Installation de KEneYa WorkFlow sous Linux, via Docker Compose.
#
#   ./install.sh
#
# Le script est idempotent : il peut etre relance sans risque sur une
# installation existante (la base n'est reinitialisee qu'a la premiere
# installation).

set -euo pipefail

cd "$(dirname "$0")"

compose() {
    if docker compose version >/dev/null 2>&1; then
        docker compose "$@"
    elif command -v docker-compose >/dev/null 2>&1; then
        docker-compose "$@"
    else
        echo "Erreur : ni « docker compose » ni « docker-compose » n'est disponible." >&2
        exit 1
    fi
}

echo "==> Verification de Docker"
if ! command -v docker >/dev/null 2>&1; then
    echo "Erreur : Docker n'est pas installe. Voir la section « Installation sans Docker » du README." >&2
    exit 1
fi

echo "==> Preparation du fichier .env"
if [ ! -f .env ]; then
    cp .env.example .env
    echo "    .env cree a partir de .env.example — pensez a y changer les mots de passe."
else
    echo "    .env existe deja, il est conserve."
fi

echo "==> Construction des conteneurs"
compose build

echo "==> Demarrage de la pile (app, web, db)"
compose up -d

echo "==> Attente de la base de donnees"
for _ in $(seq 1 60); do
    if compose exec -T app php -r 'exit(0);' >/dev/null 2>&1; then
        break
    fi
    sleep 2
done

echo "==> Generation de la cle applicative"
if grep -qE '^APP_KEY=.+$' .env; then
    echo "    APP_KEY deja definie, elle est conservee."
else
    compose exec -T app php artisan key:generate --force
fi

echo "==> Migrations et donnees initiales"
compose exec -T app php artisan migrate --seed --force

echo "==> Mise en cache de la configuration"
compose exec -T app php artisan config:cache
compose exec -T app php artisan route:cache
compose exec -T app php artisan view:cache

PORT="$(grep -E '^APP_HTTP_PORT=' .env | cut -d= -f2 || true)"
PORT="${PORT:-8080}"

cat <<EOF

======================================================================
  KEneYa WorkFlow est installe.

  Application      : http://localhost:${PORT}
  Salle d'attente  : http://localhost:${PORT}/board

  Comptes de demonstration (mot de passe : valeur de SEED_DEFAULT_PASSWORD) :
    admin@keneya.local        -> /admin
    accueil@keneya.local      -> /reception
    medecine@keneya.local     -> /service

  Changez ces mots de passe avant toute mise en service reelle.
======================================================================
EOF
