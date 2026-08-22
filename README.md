# KƐnƐya WorkFlow

Gestion de file d'attente hospitalière avec renvoi inter-services et **dossier
patient unique**, développée par AXESs pour l'**Hôpital Fousseyni Daou de Kayes**
(Mali), et conçue pour être réutilisée dans d'autres établissements maliens.

> **Nom du produit** : `KƐnƐya WorkFlow` (affiché tel quel dans l'interface et la
> documentation).
> **Identifiant technique** : `keneya-workflow` — le caractère `Ɛ` n'apparaît
> jamais dans le code, les configurations ou les identifiants système.

---

## Sommaire

1. [Principe : un rôle, une interface](#1-principe--un-rôle-une-interface)
2. [Pile technique](#2-pile-technique)
3. [Installation avec Docker (recommandé)](#3-installation-avec-docker-recommandé)
4. [Installation sans Docker](#4-installation-sans-docker)
5. [Configuration](#5-configuration)
6. [Passerelle SMS](#6-passerelle-sms)
7. [Sauvegardes](#7-sauvegardes)
8. [Modèle de données](#8-modèle-de-données)
9. [Flux de renvoi](#9-flux-de-renvoi)
10. [Tests](#10-tests)
11. [Organisation du code](#11-organisation-du-code)

---

## 1. Principe : un rôle, une interface

Contrainte de conception centrale : **chaque rôle n'a accès qu'à sa propre
interface**. Il n'y a aucune navigation croisée — pas de menu partagé, pas de
lien vers un autre module, pas de tableau de bord générique. Chaque poste
(tablette ou PC dédié) n'affiche que l'interface du rôle connecté.

| Rôle | Interface unique | Contenu |
|---|---|---|
| `admin` | `/admin` | Services, réceptionnistes, médecins (avec réaffectation de service), vue globale des patients avec accès à tout dossier. |
| `receptionist` | `/reception` | Enregistrement patient, enregistrement visiteur, passages du jour, et l'écran de salle d'attente surveillé depuis le poste d'accueil. |
| `doctor` | `/service` | File d'attente du service, renvois entrants et sortants, « appeler le suivant », « envoyer vers un service », « renvoyer un résultat », et le dossier patient dans un panneau de la même page. |

Mise en œuvre :

- Après authentification, `HomeController` redirige immédiatement vers `/admin`,
  `/reception` ou `/service` selon le rôle — jamais vers une page commune.
- Le middleware `EnsureRoleScope` (alias `role.scope`) protège chaque groupe de
  routes. Un utilisateur qui tape une autre URL à la main est **redirigé vers sa
  propre interface avec un message clair**, et non bloqué sur une erreur 403 :
  le personnel hospitalier ne doit jamais rester devant une page d'erreur
  technique.
- Un médecin rattaché à plusieurs services reste sur `/service`, avec un
  sélecteur limité à **ses** services (composant `ServiceSelector`, adossé au
  trait `ScopedToOwnService` qui refuse tout autre `service_id`, même forcé côté
  client).
- `/board` est l'affichage public de la salle d'attente, sans authentification.
  Ce n'est pas une interface « de rôle » : c'est un écran mural, également
  visible depuis le poste d'accueil.

## 2. Pile technique

| Élément | Choix |
|---|---|
| Backend | Laravel 12, PHP 8.3 |
| Frontend | Livewire 3 + Alpine (embarqué par Livewire) |
| Base de données | MySQL / MariaDB |
| Rôles et permissions | `spatie/laravel-permission` |
| SMS | `App\Services\SmsGateway` → API HTTP de SMSGate |

**Aucune pipeline de build JS.** Pas de React, pas de Vue, pas de Vite : toute
la logique métier reste côté PHP et la feuille de style est servie telle quelle
depuis `public/css/app.css`. Un serveur sans connectivité internet peut donc
déployer l'application sans jamais lancer `npm install`.

L'interface est pensée **mobile et tablette d'abord** : zones tactiles d'au
moins 48 px, boutons larges, et aucune interaction dépendant du survol de la
souris.

## 3. Installation avec Docker (recommandé)

Trois services : `app` (PHP-FPM 8.3 + Laravel), `web` (Nginx), `db`
(MariaDB). Le `docker-compose.yml` tourne **à l'identique** sous Docker Engine
(Linux) et Docker Desktop / WSL2 (Windows), sans modification.

### Linux

```bash
git clone <url-du-depot> keneya-workflow
cd keneya-workflow
cp .env.example .env      # puis éditer les mots de passe
./install.sh
```

### Windows

```powershell
git clone <url-du-depot> keneya-workflow
cd keneya-workflow
Copy-Item .env.example .env    # puis éditer les mots de passe
powershell -ExecutionPolicy Bypass -File .\install.ps1
```

Les deux scripts font la même chose : construction des conteneurs, démarrage de
la pile, génération de `APP_KEY`, `php artisan migrate --seed`, puis mise en
cache de la configuration. Ils sont idempotents et peuvent être relancés.

L'application est alors disponible sur `http://localhost:8080` (port réglable
par `APP_HTTP_PORT`), et l'écran de salle d'attente sur
`http://localhost:8080/board`.

### Commandes courantes

```bash
docker compose up -d                            # démarrer
docker compose down                             # arrêter
docker compose logs -f app                      # journaux applicatifs
docker compose exec app php artisan migrate     # migrations
docker compose exec app php artisan tinker      # console
```

Sous Windows, les mêmes commandes fonctionnent telles quelles dans PowerShell.

## 4. Installation sans Docker

À utiliser si Docker n'est pas installable sur un site donné.

Prérequis communs : PHP 8.3 avec les extensions `pdo_mysql`, `mbstring`,
`intl`, `zip`, `bcmath`, `openssl`, `fileinfo` ; Composer 2 ; MySQL 8 ou
MariaDB 10.6+.

### Linux — Nginx + PHP-FPM

```bash
sudo apt install php8.3-fpm php8.3-mysql php8.3-mbstring php8.3-intl \
                 php8.3-zip php8.3-bcmath nginx mariadb-server

git clone <url-du-depot> /var/www/keneya-workflow
cd /var/www/keneya-workflow

composer install --no-dev --optimize-autoloader
cp .env.example .env        # DB_HOST=127.0.0.1
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache && php artisan route:cache && php artisan view:cache

sudo chown -R www-data:www-data storage bootstrap/cache
```

Serveur virtuel Nginx (`/etc/nginx/sites-available/keneya-workflow`) — la
configuration de `docker/nginx/default.conf` sert de base ; il suffit de
remplacer `fastcgi_pass app:9000;` par
`fastcgi_pass unix:/run/php/php8.3-fpm.sock;` et d'adapter `root` :

```nginx
server {
    listen 80;
    server_name keneya.hopital.local;
    root /var/www/keneya-workflow/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

### Windows — IIS + PHP, ou Apache

**IIS + PHP (FastCGI)**

1. Installer PHP 8.3 (build NTS, x64) dans `C:\php`, activer les extensions
   ci-dessus dans `php.ini`.
2. Dans le Gestionnaire IIS : *Mappages de gestionnaires* → *Ajouter un mappage
   de module* → chemin `*.php`, module `FastCgiModule`, exécutable
   `C:\php\php-cgi.exe`.
3. Créer un site dont le répertoire physique est
   `C:\inetpub\keneya-workflow\public`.
4. Installer le module **URL Rewrite** et déposer dans `public\web.config` la
   règle de réécriture Laravel :

```xml
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
  <system.webServer>
    <rewrite>
      <rules>
        <rule name="Laravel" stopProcessing="true">
          <match url="^" />
          <conditions logicalGrouping="MatchAll">
            <add input="{REQUEST_FILENAME}" matchType="IsDirectory" negate="true" />
            <add input="{REQUEST_FILENAME}" matchType="IsFile" negate="true" />
          </conditions>
          <action type="Rewrite" url="index.php" />
        </rule>
      </rules>
    </rewrite>
  </system.webServer>
</configuration>
```

5. Donner au compte `IIS_IUSRS` les droits d'écriture sur `storage\` et
   `bootstrap\cache\`.

**Apache (type Laragon en usage serveur)**

Installer Laragon avec PHP 8.3 et MySQL, placer le projet dans
`C:\laragon\www\keneya-workflow`, puis pointer le `DocumentRoot` sur le
sous-dossier `public` :

```apache
<VirtualHost *:80>
    ServerName keneya.hopital.local
    DocumentRoot "C:/laragon/www/keneya-workflow/public"
    <Directory "C:/laragon/www/keneya-workflow/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

Le `.htaccess` livré par Laravel dans `public/` gère la réécriture ; le module
`mod_rewrite` doit être actif.

Ensuite, dans les deux cas :

```powershell
composer install --no-dev --optimize-autoloader
Copy-Item .env.example .env      # DB_HOST=127.0.0.1
php artisan key:generate
php artisan migrate --seed --force
php artisan config:cache; php artisan route:cache; php artisan view:cache
```

## 5. Configuration

Tout se règle dans `.env` (voir `.env.example`, entièrement commenté). Aucun
identifiant n'est écrit en dur dans le code.

| Variable | Rôle |
|---|---|
| `KENEYA_NAME` | Nom affiché dans l'interface et dans les SMS. |
| `KENEYA_HOSPITAL` | Nom de l'établissement. |
| `KENEYA_CODE_PREFIX` | Préfixe des identifiants de dossier (`HFD` → `HFD-00001`). **À changer pour un autre établissement.** |
| `KENEYA_POLL_INTERVAL` | Rafraîchissement Livewire des écrans de travail (défaut `10s`). |
| `KENEYA_BOARD_POLL_INTERVAL` | Rafraîchissement de l'écran de salle d'attente (défaut `5s`). |
| `SEED_DEFAULT_PASSWORD` | Mot de passe des comptes créés par les seeders. |
| `APP_HTTP_PORT` | Port publié par Docker Compose (défaut `8080`). |

### Comptes de démonstration

`php artisan db:seed` crée les six services de départ (Médecine Générale,
Urgences, Maternité, Administration, Échographie, Laboratoire) ainsi que :

| Compte | Rôle | Interface |
|---|---|---|
| `admin@keneya.local` | `admin` | `/admin` |
| `accueil@keneya.local` | `receptionist` | `/reception` |
| `medecine@keneya.local` | `doctor` | `/service` (Médecine Générale) |
| `urgences@keneya.local` | `doctor` | `/service` (Urgences) |
| `maternite@keneya.local` | `doctor` | `/service` (Maternité) |
| `echographie@keneya.local` | `doctor` | `/service` (Échographie) |
| `laboratoire@keneya.local` | `doctor` | `/service` (Laboratoire) |

Le mot de passe est la valeur de `SEED_DEFAULT_PASSWORD`. **Changez ces comptes
avant toute mise en service réelle.**

## 6. Passerelle SMS

Les SMS partent par **SMSGate**, une application Android auto-hébergée : un
téléphone posé sur le réseau de l'hôpital expose une API HTTP, et
`App\Services\SmsGateway::send($to, $text)` l'appelle.

```env
SMSGATE_ENABLED=true
SMSGATE_URL=http://192.168.1.50:8080
SMSGATE_LOGIN=sms
SMSGATE_PASSWORD=…
SMSGATE_COUNTRY_CODE=223
```

- Les numéros saisis au comptoir à 8 chiffres sont automatiquement mis au
  format international (`76445566` → `+22376445566`).
- Une passerelle injoignable **ne fait jamais échouer l'acte métier** : le
  patient est enregistré, le renvoi est créé, et l'échec d'envoi est simplement
  journalisé.
- `SMSGATE_ENABLED=false` désactive complètement les envois (formation,
  recette). Les tests tournent toujours avec les SMS désactivés.

## 7. Sauvegardes

Les données vivent dans le volume Docker dédié **`keneya_db`**, distinct du
code : `docker compose down` ne l'efface pas (`docker compose down -v`, si).

Export `.sql` — la même commande des deux côtés :

```bash
docker compose exec -T db mariadb-dump \
    --user=keneya --password=<mot-de-passe> --single-transaction \
    keneya_workflow > keneya_workflow.sql
```

Deux scripts prêts à l'emploi horodatent l'export et ne conservent que les 30
dernières sauvegardes :

```bash
./scripts/backup.sh /var/sauvegardes/keneya                       # Linux
powershell -File .\scripts\backup.ps1 -Destination D:\sauvegardes # Windows
```

**Planification — Linux (cron), tous les jours à 22h00 :**

```cron
0 22 * * * cd /var/www/keneya-workflow && ./scripts/backup.sh /var/sauvegardes/keneya >> /var/log/keneya-backup.log 2>&1
```

**Planification — Windows (Planificateur de tâches) :**

```powershell
$action  = New-ScheduledTaskAction -Execute 'powershell.exe' `
    -Argument '-ExecutionPolicy Bypass -File C:\keneya-workflow\scripts\backup.ps1 -Destination D:\sauvegardes'
$trigger = New-ScheduledTaskTrigger -Daily -At 22:00
Register-ScheduledTask -TaskName 'Sauvegarde KEneYa WorkFlow' -Action $action -Trigger $trigger -RunLevel Highest
```

Restauration :

```bash
docker compose exec -T db mariadb --user=keneya --password=<mot-de-passe> \
    keneya_workflow < keneya_workflow.sql
```

## 8. Modèle de données

| Table | Rôle |
|---|---|
| `services` | Services de l'établissement, `kind` ∈ {`clinique`, `plateau_technique`}. |
| `doctors` | Rattachement d'un compte à un service, réaffectable à tout moment. Un médecin multi-services a plusieurs lignes. |
| `receptionists` | Rattachement d'un compte au rôle d'accueil. |
| `patients` | Dossier unique et permanent : `patient_code` (`HFD-00001`), plus `crno`, le numéro de dossier papier saisi à la main. |
| `visitors` | Fiche visiteur (`HFD-V-00001`), sans dossier médical ni ticket. |
| `referrals` | Renvoi d'un service à un autre, avec instructions puis résultat. |
| `patient_history` | Journal **append-only** du parcours du patient. |

Deux garanties structurelles :

- **`patient_code` est attribué dans `PatientObserver::creating`**, jamais dans
  un contrôleur. Quel que soit le point d'entrée — formulaire, seeder, import,
  `tinker` — un patient ne peut pas exister sans identifiant unique.
- **`patient_history` est append-only** : `PatientHistoryObserver` lève une
  exception sur toute tentative de mise à jour ou de suppression. Le seul point
  d'écriture est `PatientHistoryRecorder`.

## 9. Flux de renvoi

Le `patient_id` **ne change jamais** : c'est le même dossier qui traverse les
services, jamais dupliqué.

1. Le médecin du service source choisit un patient de sa file, un service
   destinataire et saisit des instructions.
2. `App\Actions\SendReferral`, en une transaction :
   - crée la ligne `referrals` (`status = pending`) ;
   - met à jour `patients.service_id` et génère un nouveau `token` dans la file
     du service destinataire ;
   - insère une ligne `patient_history` (`type = referral_sent`) ;
   - puis, hors transaction, envoie un SMS au patient.
3. Le praticien du service destinataire voit le renvoi dans son panneau
   « Renvois en attente » de sa propre interface `/service`, filtré sur son
   `service_id`, et saisit le résultat.
4. `App\Actions\CompleteReferral`, en une transaction :
   - met à jour `referrals` (`status = done`, `result_text`,
     `completed_by_doctor_id`, `completed_at`) ;
   - insère une ligne `patient_history` (`type = referral_result`) ;
   - puis notifie le prescripteur par SMS si `doctors.phone` est renseigné.

Le résultat apparaît dans le panneau « Résultats reçus » du prescripteur au
prochain cycle de `wire:poll` — **pas de WebSocket dans cette phase**.

## 10. Tests

```bash
php artisan test                        # sans Docker
docker compose exec app php artisan test  # avec Docker
```

La suite couvre notamment :

| Fichier | Objet |
|---|---|
| `RoleScopeTest` | Cloisonnement des interfaces, redirections, `/board` public. |
| `PatientCodeTest` | Génération du `patient_code`, unicité, préfixe configurable. |
| `ReferralFlowTest` | Transaction de renvoi complète, notifications, historique append-only. |
| `ServiceInterfaceTest` | Appel du suivant, renvoi, saisie du résultat, refus d'un service qui n'est pas le sien. |
| `ReceptionInterfaceTest` | Enregistrement patient et visiteur, files par service, salle d'attente. |
| `AdminInterfaceTest` | Services, médecins, réaffectation, réceptionnistes, dossiers. |
| `AcceptanceScenarioTest` | Le scénario d'acceptation de bout en bout, dans l'ordre. |
| `SmsGatewayTest` | Format international, passerelle désactivée ou injoignable. |

Les tests tournent sur SQLite en mémoire et n'envoient jamais de SMS.

## 11. Organisation du code

Aucune logique métier ne vit dans les vues Blade ou Livewire : les composants
valident puis délèguent à une action ou à un service.

```
app/
├── Actions/            RegisterPatient, RegisterVisitor, CallNextPatient,
│                       SendReferral, CompleteReferral  (transactions métier)
├── Http/
│   ├── Controllers/    Contrôleurs minces, une interface par rôle
│   ├── Middleware/     EnsureRoleScope (cloisonnement), RedirectIfAuthenticated
│   └── Requests/       LoginRequest
├── Livewire/
│   ├── Admin/          ServiceManager, DoctorManager, ReceptionistManager,
│   │                   PatientDirectory
│   ├── Board/          WaitingBoard (public et poste d'accueil)
│   ├── Reception/      PatientRegistrationForm, VisitorRegistrationForm,
│   │                   TodayVisits
│   └── Service/        ServiceQueue, IncomingReferrals, OutgoingReferrals,
│                       PatientRecordPanel, ServiceSelector
│                       + Concerns/ScopedToOwnService
├── Models/             Service, Doctor, Receptionist, Patient, Visitor,
│                       Referral, PatientHistory, User
├── Observers/          PatientObserver (patient_code), VisitorObserver,
│                       PatientHistoryObserver (append-only)
├── Services/           SmsGateway, TokenAllocator, PatientCodeGenerator,
│                       PatientHistoryRecorder
└── Support/            Roles (rôles ↔ interfaces)
```

L'interface est intégralement en français, y compris les messages de
validation et d'erreur.

---

© AXESs — KƐnƐya WorkFlow.
