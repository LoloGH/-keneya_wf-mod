#!/usr/bin/env bash
#
# Verification d'un deploiement KEneYa WorkFlow, sur une base jetable.
#
#   ./scripts/verify-deploy.sh [url-de-base]
#
# Enchaine les controles a passer avant de remplacer une version en service :
# migrations dans les deux sens contre le vrai moteur, reprise des donnees
# v1 -> visits, suite de tests, cloisonnement des roles, plafonds d'envoi.
#
# Sort en code 1 au premier controle rouge, et affiche un verdict final.
#
# ---------------------------------------------------------------------------
# CE SCRIPT DETRUIT LA BASE QU'IL VERIFIE
# ---------------------------------------------------------------------------
#
# Son controle n° 2 lance `migrate:fresh --seed`, puis un rollback complet
# suivi d'une re-migration. C'est le seul moyen de verifier que les migrations
# tiennent dans les deux sens contre le moteur reel — et cela vide toutes les
# tables et reinsere les donnees de demonstration.
#
# Il s'adresse donc a une pile montee pour la verification, jamais a celle qui
# porte les dossiers d'un etablissement. Son en-tete disait « a lancer sur le
# serveur cible », ce qui invitait exactement au geste qu'il ne faut pas
# faire : le lancer apres une mise a jour, pour verifier qu'elle s'est bien
# passee, et perdre la base au moment ou l'on croyait la controler.
#
# D'ou le garde-fou ci-dessous : le script compte les patients avant de
# commencer et refuse de continuer s'il en trouve. `--base-jetable` passe
# outre, et il faut le taper.

set -uo pipefail
cd "$(dirname "$0")/.."

BASE_URL=""
JETABLE=0

for argument in "$@"; do
    case "$argument" in
        --base-jetable) JETABLE=1 ;;
        *) BASE_URL="$argument" ;;
    esac
done

BASE_URL="${BASE_URL:-http://localhost:${APP_HTTP_PORT:-8080}}"
ROUGE=0

compose() {
    if docker compose version >/dev/null 2>&1; then docker compose "$@"
    else docker-compose "$@"; fi
}

app() { compose exec -T app "$@"; }

titre() { printf '\n\033[1m== %s ==\033[0m\n' "$1"; }
vert()  { printf '  \033[32mOK\033[0m   %s\n' "$1"; }
rouge() { printf '  \033[31mNOK\033[0m  %s\n' "$1"; ROUGE=1; }

# --------------------------------------------------------------- 1. La pile
titre "1. Pile Docker"
if compose ps --status running --services 2>/dev/null | grep -q app; then
    vert "les conteneurs tournent"
else
    rouge "la pile n'est pas demarree — lancez ./install.sh"
    exit 1
fi

printf '  moteur : '
app php -r 'echo DB::connection()->getDriverName();' 2>/dev/null \
    || app php artisan tinker --execute='echo DB::selectOne("select version() v")->v;' 2>/dev/null
echo

# ------------------------------------- Garde-fou : la base doit etre jetable
#
# Le compte des patients plutot qu'un reglage d'environnement : `APP_ENV` se
# copie d'un serveur a l'autre avec le reste du `.env`, et se trompe donc en
# silence. Un dossier patient, lui, ne ment pas sur ce que contient la base.
#
# La requete passe par le modele et non par un `select` direct : elle doit
# echouer franchement si la base est injoignable, plutot que de rendre zero et
# de laisser croire que le terrain est libre.
PATIENTS="$(app php artisan tinker --execute='echo \App\Models\Patient::count();' 2>/dev/null | tr -dc '0-9')"

if [ -n "$PATIENTS" ] && [ "$PATIENTS" -gt 0 ] 2>/dev/null && [ "$JETABLE" != 1 ]; then
    printf '\n\033[31mArret.\033[0m Cette base contient %s dossier(s) patient.\n' "$PATIENTS" >&2
    cat >&2 <<'FIN'

  Ce script vide la base : son controle des migrations lance `migrate:fresh`
  puis un rollback complet. Il verifie une pile montee pour la verification,
  pas une installation en service.

  Pour verifier qu'une mise a jour s'est bien passee sans rien detruire :

      docker compose ps
      docker compose exec -T app php artisan migrate:status | tail -5
      curl -so /dev/null -w '%{http_code}\n' http://localhost:8080

  Si cette base est bien jetable, relancez avec --base-jetable.

FIN
    exit 1
fi

# ------------------------------------------------------- 2. Aller et retour
titre "2. Migrations dans les deux sens"
if app php artisan migrate:fresh --seed --force >/dev/null 2>&1; then
    vert "migrate:fresh --seed"
else
    rouge "migrate:fresh a echoue"
fi

ATTENDU=$(ls database/migrations/*.php | wc -l | tr -d ' ')
APPLIQUEES=$(app php artisan migrate:status 2>/dev/null | grep -c 'Ran' || echo 0)
if [ "$APPLIQUEES" -eq "$ATTENDU" ]; then
    vert "$APPLIQUEES migrations appliquees (sur $ATTENDU attendues)"
else
    rouge "$APPLIQUEES migrations appliquees, $ATTENDU attendues"
fi

if app php artisan migrate:rollback --step="$ATTENDU" --force >/dev/null 2>&1 \
   && app php artisan migrate --seed --force >/dev/null 2>&1; then
    vert "rollback complet puis re-migration"
else
    rouge "l'aller-retour des migrations a echoue"
fi

# -------------------------------------------- 3. Reprise des donnees v1
titre "3. Reprise des donnees patients -> visits"
RESULTAT=$(app php artisan tinker --execute='
$avant = DB::table("patients")->count();
$visites = DB::table("visits")->count();
$orphelines = DB::table("visits")->whereNotIn("patient_id", DB::table("patients")->pluck("id"))->count();
$colonnes = collect(Schema::getColumnListing("patients"))->intersect(["service_id","token","status"])->count();
echo "$avant|$visites|$orphelines|$colonnes";
' 2>/dev/null | tail -1)

IFS='|' read -r PATIENTS VISITES ORPHELINES RESIDU <<< "$RESULTAT"
[ "${RESIDU:-9}" = "0" ] && vert "patients ne porte plus service_id/token/status" \
                         || rouge "patients porte encore des colonnes de passage"
[ "${ORPHELINES:-9}" = "0" ] && vert "aucune visite orpheline" \
                            || rouge "$ORPHELINES visite(s) sans patient"

# --------------------------------------------------------- 4. Les tests
titre "4. Suite de tests"
SORTIE=$(app php artisan test 2>&1 | tail -5)
echo "$SORTIE" | sed 's/^/  /'
echo "$SORTIE" | grep -qE 'Tests:.*[0-9]+ passed' && ! echo "$SORTIE" | grep -q 'failed' \
    && vert "suite au vert" || rouge "des tests echouent"

# --------------------------------------------------- 5. Cloisonnement
titre "5. Cloisonnement des roles ($BASE_URL)"
BISCUITS=$(mktemp)
MDP="${SEED_DEFAULT_PASSWORD:-$(grep -E '^SEED_DEFAULT_PASSWORD=' .env | cut -d= -f2- | tr -d '"')}"

JETON=$(curl -sk -c "$BISCUITS" "$BASE_URL/connexion" | grep -oP 'name="_token" value="\K[^"]+' | head -1)
curl -sk -b "$BISCUITS" -c "$BISCUITS" -o /dev/null -X POST "$BASE_URL/connexion" \
    -d "_token=$JETON" -d "email=accueil@keneya.local" -d "password=$MDP"

if [ "$(curl -sk -b "$BISCUITS" -o /dev/null -w '%{http_code}' "$BASE_URL/reception")" = "200" ]; then
    vert "la receptionniste atteint /reception"
else
    rouge "la receptionniste n'atteint pas /reception (mot de passe seed ?)"
fi

for URL in /admin /service /service/pieces-jointes/1 /admin/pieces-jointes/1 /service/ordonnances/1/pdf; do
    CODE=$(curl -sk -b "$BISCUITS" -o /dev/null -w '%{http_code}' "$BASE_URL$URL")
    CIBLE=$(curl -sk -b "$BISCUITS" -o /dev/null -w '%{redirect_url}' "$BASE_URL$URL")
    if [ "$CODE" = "302" ] && [[ "$CIBLE" == */reception ]]; then
        vert "$URL -> redirige vers /reception"
    else
        rouge "$URL -> $CODE ${CIBLE:-sans redirection} (attendu : 302 vers /reception)"
    fi
done
rm -f "$BISCUITS"

# ------------------------------------------------ 6. Plafonds d'envoi
titre "6. Plafonds d'envoi"
NGINX=$(compose exec -T web sh -c "grep -oE 'client_max_body_size [0-9]+M' /etc/nginx/conf.d/default.conf | awk '{print \$2}'" 2>/dev/null | tr -d 'M\r')
UPLOAD=$(app php -r 'echo (int) ini_get("upload_max_filesize");' 2>/dev/null | tr -d '\r')
POST=$(app php -r 'echo (int) ini_get("post_max_size");' 2>/dev/null | tr -d '\r')
APPLI=$(app php -r 'require "vendor/autoload.php"; echo App\Models\Attachment::MAX_SIZE_KB / 1024;' 2>/dev/null | tr -d '\r')

printf '  Nginx %sM | post %sM | upload %sM | applicatif %sM\n' \
    "${NGINX:-?}" "${POST:-?}" "${UPLOAD:-?}" "${APPLI:-?}"

if [ -n "${NGINX:-}" ] && [ -n "${POST:-}" ] && [ -n "${UPLOAD:-}" ] && [ -n "${APPLI:-}" ] \
   && [ "$NGINX" -ge "$POST" ] && [ "$POST" -ge "$UPLOAD" ] && [ "$UPLOAD" -gt "$APPLI" ]; then
    vert "serveur web >= post >= upload > applicatif"
else
    rouge "plafonds incoherents — un fichier accepte par le formulaire serait rejete"
fi

# ------------------------------------------------------------- Verdict
titre "Verdict"
if [ "$ROUGE" -eq 0 ]; then
    printf '  \033[32mTout est vert.\033[0m Le deploiement peut remplacer la version en service.\n\n'
    exit 0
fi

printf '  \033[31mAu moins un controle est rouge.\033[0m Ne remplacez pas la version en service.\n\n'
exit 1
