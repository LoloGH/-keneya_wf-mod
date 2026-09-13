#!/bin/sh
# Point d'entree du service `app` dans la pile de developpement.
#
# Il reconstruit les trois caches de demarrage de Laravel avant de lancer
# php-fpm, puis passe la main.
#
# ---------------------------------------------------------------------------
# Pourquoi
# ---------------------------------------------------------------------------
#
# Le code est monte depuis l'hote, et sur Docker Desktop chaque acces fichier
# traverse un pont. Sans ces caches, chaque requete relit la vingtaine de
# fichiers de `config/`, redecouvre les paquets et reconstruit la table des
# routes. Mesure de la phase `bootstrap()` de Laravel, page de connexion :
#
#     sans cache                      270 - 300 ms
#     config en cache                  80 -  89 ms
#     config + routes + evenements     80 - 109 ms, et bien plus regulier
#
# Le cache d'evenements a ete verifie a part, en alternant les deux etats pour
# annuler la derive de la machine : mediane de bout en bout 0,249 s et 0,295 s
# avec, contre 0,363 s et 0,686 s sans. Il reste donc, malgre sa construction
# lente.
#
# Contrepartie : le demarrage du conteneur prend une minute de plus, le temps
# de construire les trois caches depuis le montage. C'est le bon sens de
# l'echange — on redemarre rarement, on charge des pages tout le temps.
#
# ---------------------------------------------------------------------------
# Pourquoi au demarrage, et pas une commande a retenir
# ---------------------------------------------------------------------------
#
# Un cache de configuration fige `.env`, un cache de routes fige `routes/`.
# Laisser leur reconstruction a une commande a taper, c'est reinstaller le
# piege que `php-dev.ini` vient de retirer : on ajoute une route, on obtient
# un 404, et rien ne dit pourquoi.
#
# En les reconstruisant a chaque demarrage du conteneur, la regle tient en une
# phrase : ce qui se modifie tous les jours — gabarits, classes, styles — est
# visible au rechargement de la page ; ce qui touche `.env`, `config/`,
# `routes/` ou `vendor/` demande un `docker compose restart app`.
#
# Ce fichier n'est utilise que par `docker-compose.yml`. Un deploiement en
# service a son propre entrypoint et n'est pas concerne.
set -e

echo "[dev] reconstruction des caches de demarrage"

# `|| true` : un cache qui ne se construit pas doit ralentir la pile, jamais
# l'empecher de demarrer. Laravel retombe alors sur la lecture directe.
php artisan config:cache || true
php artisan route:cache || true
php artisan event:cache || true

# Les gabarits compiles, eux, ne sont pas mis en cache ici : ils sont exclus
# d'opcache (voir `opcache-dev-exclusions.txt`) precisement pour qu'une
# modification apparaisse sans rien relancer.
php artisan view:clear || true

exec "$@"
