#!/usr/bin/env bash
#
# Lance une des deux suites de tests de l'assemblage, depuis n'importe ou.
#
#   scripts/tests.sh hote  [--filter=...]   # WorkFlow, module monte compris
#   scripts/tests.sh dme   [--filter=...]   # le module seul
#   scripts/tests.sh                        # les deux
#
# Pourquoi ce script plutot que les deux commandes du README.
#
# `docker compose exec app php artisan test` et `docker compose run --rm dme
# ./vendor/bin/phpunit` supposent deux choses qui ne sont pas toujours vraies :
# qu'on se trouve dans la copie principale du depot, et que la pile tourne.
# Le travail se fait pourtant souvent dans un `git worktree` — un repertoire
# sous `.claude/worktrees/`, sans `.env` et sans `vendor/`. Chaque tentative se
# solde alors par une erreur qui ne dit pas sa cause, et la conclusion
# tentante — « les tests ne se lancent pas d'ici » — est fausse.
#
# Le script monte donc explicitement ce qu'il faut : la copie de travail
# courante, le `vendor/` et le `.env` de la copie principale. Il n'a besoin
# d'aucun conteneur en marche, seulement de l'image, que `docker compose build`
# produit deja.
#
# Le module, lui, n'a plus a etre monte a part : il vit dans le depot depuis
# la v3.4.2, sous `modules/dme`, et arrive donc avec la copie de travail. Le
# worktree en herite aussi, ce qui retire une des raisons pour lesquelles ce
# script existait.
set -euo pipefail

# Chemin de style Docker (C:/... plutot que /c/...) : sous Git Bash, MSYS
# reecrit les arguments qui ressemblent a des chemins Unix, et `-v /c/...`
# arrive au démon sous une forme qu'il refuse.
chemin_docker() {
  if command -v cygpath >/dev/null 2>&1; then
    cygpath -m "$1"
  else
    printf '%s' "$1"
  fi
}

racine="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# La copie principale, celle qui porte `.env` et `vendor/`. Dans un worktree,
# `git rev-parse --git-common-dir` designe le `.git` du depot d'origine.
git_commun="$(cd "$racine" && git rev-parse --git-common-dir 2>/dev/null || echo '')"
if [ -n "$git_commun" ]; then
  principal="$(cd "$racine" && cd "$(dirname "$git_commun")" && pwd)"
else
  principal="$racine"
fi

image="${KENEYA_IMAGE:-keneya-wf-mod-app}"

if [ ! -d "$racine/modules/dme" ]; then
  echo "Module DME introuvable dans $racine/modules/dme." >&2
  echo "Depuis la v3.4.2 il fait partie de ce depot : un 'git pull' devrait suffire." >&2
  exit 1
fi

for requis in "$principal/vendor" "$principal/.env"; do
  if [ ! -e "$requis" ]; then
    echo "$requis manquant : lancez d'abord 'composer install' et creez le .env dans $principal." >&2
    exit 1
  fi
done

if ! docker image inspect "$image" >/dev/null 2>&1; then
  echo "Image $image absente : lancez 'docker compose build' dans $principal." >&2
  exit 1
fi

# Les dependances de developpement, remises en place quand elles manquent.
#
# `scripts/update.sh` installe avec `--no-dev` : un serveur qui recoit des
# patients n'a besoin ni de PHPUnit ni de Pint. La consequence tombe sur la
# machine de developpement, ou c'est le meme `vendor/` qui sert aux deux — une
# mise a jour emporte le lanceur de tests avec elle.
#
# Et la panne ne se presente pas comme telle. `php artisan test` disparait avec
# Collision, et Laravel repond alors « Command "test" is not defined. Did you
# mean make:test ? » : rien, dans cette phrase, ne mene a `composer install`.
# Le script repose donc la question lui-meme, une fois, au lieu de laisser
# chacun la redecouvrir.
restaure_dev() {
  local ou="$1" dans="$2"

  echo "== PHPUnit absent de $ou/vendor : restauration des dependances de developpement"
  MSYS_NO_PATHCONV=1 docker run --rm     -v "$(chemin_docker "$ou"):/var/www/html"     -w "$dans" --user 0:0     -e COMPOSER_ALLOW_SUPERUSER=1 --entrypoint composer     "$image" install --no-interaction --prefer-dist --no-progress
}

[ -x "$principal/vendor/bin/phpunit" ] || restaure_dev "$principal" /var/www/html
[ -x "$principal/modules/dme/vendor/bin/phpunit" ] || restaure_dev "$principal" /var/www/html/modules/dme

suite="${1:-tout}"
[ $# -gt 0 ] && shift || true

lance_hote() {
  echo "== Suite hote (WorkFlow), depuis $racine"
  MSYS_NO_PATHCONV=1 docker run --rm \
    -v "$(chemin_docker "$racine"):/var/www/html" \
    -v "$(chemin_docker "$principal/vendor"):/var/www/html/vendor:ro" \
    -v "$(chemin_docker "$principal/.env"):/var/www/html/.env:ro" \
    -w /var/www/html --user 0:0 --entrypoint php \
    "$image" artisan test "$@"
}

lance_dme() {
  echo "== Suite du module DME, depuis $racine/modules/dme"
  MSYS_NO_PATHCONV=1 docker run --rm \
    -v "$(chemin_docker "$racine"):/var/www/html" \
    -w /var/www/html/modules/dme --user 0:0 \
    -e COMPOSER_ALLOW_SUPERUSER=1 --entrypoint ./vendor/bin/phpunit \
    "$image" "$@"
}

case "$suite" in
  hote|host|wf) lance_hote "$@" ;;
  dme|module)   lance_dme "$@" ;;
  tout|all)     lance_hote; lance_dme ;;
  *)
    echo "Usage: $0 {hote|dme|tout} [options passees a la suite]" >&2
    exit 2
    ;;
esac
