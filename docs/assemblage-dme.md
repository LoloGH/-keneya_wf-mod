# Assemblage : KƐNƐYA WorkFlow + module Dossier Médical Électronique

Ce dépôt est une **copie de travail** de `keneya_workflow` dans laquelle le
module `keneya/dme` est monté. Il est volontairement séparé du dépôt d'origine :
rien n'y est reversé tant que l'ensemble n'a pas été validé.

Le module vit dans un dossier voisin (`../keneya-dme_mod`) et est référencé par
un dépôt Composer de type `path`. Il n'est donc pas copié dans ce dépôt : les
deux se développent côte à côte.

**La version du module qu'exige cet assemblage est la branche
`assemblage-workflow` de [`LoloGH/-keneya-dme_mod`](https://github.com/LoloGH/-keneya-dme_mod/tree/assemblage-workflow)**,
pas encore `main`. C'est elle qui porte le préfixe `dme_` sur les tables du
module et la traduction de ses rôles ; sans elle, les deux schémas entrent en
collision. La CI la cible explicitement (`.github/workflows/ci.yml`) — à
remettre sur `main` une fois la fusion faite là-bas.

---

## 1. Ce que l'assemblage ajoute

| Question | Réponse retenue |
|---|---|
| Qui peut ouvrir un dossier médical ? | La capacité `can_access_dme`, cochée sur un type de personnel dans `/admin`. |
| Par où y entre-t-on ? | L'action « Dossier medical complet » de l'onglet **Mes patients** de `/service`. Pas d'interface de premier niveau. |
| Comment le module envoie-t-il un SMS ? | Par `SendSmsJob` de WorkFlow. Une seule file, une seule table `sms_messages`, un seul indicateur d'échecs. |
| Qui est l'utilisateur, côté module ? | Le compte WorkFlow. Même table `users`, même session, même journal d'audit. |

---

## 2. Démarrage

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan migrate --seed --force
docker compose exec app php artisan vendor:publish --tag=dme-config
docker compose exec app php artisan vendor:publish --tag=dme-assets --force
```

L'application répond sur **http://localhost:8083**.

Le port n'est pas anodin : `keneya_workflow` occupe le 8080 et
`keneya-dme_app` (le module en autonome) le 8081. Les trois piles doivent
pouvoir tourner en même temps sur le même poste. Pour la même raison, la base
de cet assemblage est publiée sur le **3309** et ses volumes Docker portent des
noms qui lui sont propres (`keneya_wf_mod_db`, `keneya_wf_mod_storage`) — un
volume partagé aurait fait écrire cette pile dans la base de production de
`keneya_workflow`, ce qui ne se voit qu'une fois le mal fait.

`vendor:publish --tag=dme-assets` n'est pas facultatif : sans lui, les pages du
module s'affichent sans feuille de style.

### Après avoir modifié le module

```bash
docker compose restart app scheduler queue-worker && docker compose restart web
```

L'image règle `opcache.validate_timestamps = 0` : PHP-FPM ne relit jamais un
fichier déjà compilé. C'est le bon réglage en production, mais le module est
monté en volume et se modifie donc à chaud — sans ce redémarrage, l'application
continue de servir l'ancien code pendant qu'`artisan test`, qui est un autre
processus, voit déjà le nouveau. On croit alors avoir corrigé un bug qui
s'affiche toujours à l'écran.

`web` est redémarré ensuite parce que Nginx résout l'adresse d'`app` une fois
pour toutes : si `app` redémarre seul, il répond 502 jusqu'à ce qu'on le
relance.

### Lancer la suite du module

```bash
docker compose run --rm dme composer install     # une seule fois
docker compose run --rm dme ./vendor/bin/phpunit
```

La suite de l'hôte traverse le module, mais toujours avec WorkFlow derrière.
Celle du module le vérifie **seul** : sans hôte pour fournir un utilisateur,
des rôles, une signature ou des coordonnées d'établissement, chaque point
d'accroche doit se rabattre proprement sur son comportement par défaut. Un
câblage manquant passe la première suite et échoue la seconde.

Le service `dme` monte le module **en écriture**, contrairement aux trois
services qui servent l'application : composer, PHPUnit et Pint écrivent tous,
alors qu'une pile qui répond à des requêtes HTTP n'a aucune raison de pouvoir
réécrire le code d'un de ses paquets. Son profil `tools` le tient hors de
`docker compose up`.

### Le montage du module dans le conteneur

`docker-compose.yml` monte `../keneya-dme_mod` sur `/var/www/keneya-dme_mod`, et
c'est **ce chemin-là** — celui vu de l'intérieur du conteneur — que déclare le
dépôt `path` de `composer.json`. Sans le montage, `composer install` échouerait
dans le conteneur alors qu'il fonctionnerait sur la machine : c'est l'erreur
classique de ce genre d'assemblage.

L'image, elle, se construit sans le module : le `Dockerfile` pose un
`composer.json` minimal à cette adresse le temps du build, et la découverte des
paquets se refait au premier démarrage, une fois le vrai module en place.

---

## 3. Cohabitation des deux schémas

Les migrations du module et celles de WorkFlow créent des tables dans **la même
base**. Six noms entraient en collision frontale — `patients`, `services`,
`appointments`, `prescriptions`, `hospitalizations`, `sms_messages` — et la
liste se serait allongée à chaque évolution de l'un ou de l'autre.

La règle posée dans le module est donc explicite :

- **les tables que le module possède portent le préfixe `dme_`**
  (`dme_patients`, `dme_consultations`, `dme_prescriptions`…) ;
- **les tables qu'il partage avec son hôte gardent leur nom** : `users`,
  `activity_log`, les tables de `spatie/laravel-permission` et
  `personal_access_tokens`.

Ce partage est le cœur de l'assemblage, pas un effet de bord :

- `users` : le praticien du dossier médical **est** le compte WorkFlow. Les
  migrations du module ajoutent à cette table les colonnes professionnelles
  (`matricule`, `first_name`, `service_id`, `is_active`, `is_on_duty`…), une par
  une et seulement si elles manquent.
- `activity_log` : c'est ce qui fait qu'une consultation ouverte dans le module
  apparaît dans le journal d'audit de `/admin`. Le module écrit sous le nom de
  journal `medical`, WorkFlow sous `keneya`, et l'écran d'administration
  restitue les deux (`Audit::LOG_NAMES`).
- les rôles et permissions : `$user->can('prescriptions.create')` répond la même
  chose des deux côtés. `DmePermissionSeeder` verse les permissions fines du
  DME dans le RBAC de WorkFlow et les rattache aux rôles `admin`, `doctor` et
  `receptionist`.

Les clés étrangères des tables du module vers `users` sont conservées ; celles
qui traverseraient la frontière dans l'autre sens n'existent pas.

---

## 4. Le modèle utilisateur

Le module ne peut pas imposer sa classe `User` à son hôte, et il ne peut pas non
plus manipuler une autre classe que celle que `Auth::user()` renvoie — sinon les
comparaisons d'identité seraient fausses et le `causer_type` du journal d'audit
divergerait.

Trois pièces règlent la question :

1. `config('dme.models.user')` désigne le modèle en vigueur ; ici
   `App\Models\User`.
2. `Keneya\Dme\Contracts\DmeUser` dit ce que le module attend de ce modèle. Les
   policies du module typent ce contrat, jamais une classe précise.
3. `Keneya\Dme\Models\Concerns\IsDmePractitioner` le remplit : relations du
   dossier médical, garde, nom d'affichage, compte actif. `App\Models\User`
   utilise ce trait.

---

## 5. Les SMS

`App\Services\Dme\WorkflowSmsDispatcher` implémente `SmsDispatcherContract` en
appelant `SendSmsJob::dispatch(...)`. La liaison est posée par
`DmeIntegrationServiceProvider`, déclaré dans `bootstrap/providers.php` : les
fournisseurs de l'application sont enregistrés **après** ceux des paquets, donc
cette liaison remplace le repli interne du module (`QueuedSmsDispatcher`).

Ce n'est pas seulement une question d'aiguillage. Le module constate lui-même
que sa file interne n'est plus l'implémentation retenue et, en conséquence,
n'enregistre ni ses commandes de suivi d'acheminement ni la tâche planifiée qui
les appelle. On peut le vérifier :

```bash
docker compose exec app php artisan schedule:list   # keneya:duty-periods:sync toutes les 5 min
docker compose exec app php artisan list keneya     # aucune commande keneya:sms:*
```

Le contexte transmis par le module (`SmsContext`) est décodé pour rattacher la
ligne `sms_messages` à l'enregistrement du DME qui l'a déclenchée, exactement
comme un SMS émis par WorkFlow.

---

## 6. Tâches planifiées

Le conteneur `scheduler` déjà en place (`php artisan schedule:work`) couvre
l'hôte **et** le module : `keneya:duty-periods:sync` y apparaît toutes les cinq
minutes. Aucun conteneur supplémentaire n'est nécessaire.

---

## 7. Tests

```bash
docker compose exec app php artisan test
```

Deux corrections ont été nécessaires pour que cette commande veuille dire
quelque chose :

- **`ext-gd`** manquait dans l'image. Aucun `composer.json` ne la déclare —
  dompdf ne le fait pas — et son absence ne se voyait qu'à l'exécution : toute
  ordonnance portant un logo, une signature ou un tampon échouait en 500. Le
  module en a besoin pour les mêmes raisons, plus le QR code de ses documents.
- **`tests/bootstrap.php`**. `docker-compose.yml` injecte le `.env` de
  l'application dans l'environnement du conteneur (`env_file`), PHP le recopie
  dans `$_SERVER`, et c'est `$_SERVER` que Laravel consulte en premier. Les
  `<env>` de `phpunit.xml`, qui n'écrivent que dans `putenv()` et `$_ENV`, ne
  faisaient pas le poids : **la suite tournait sur la base de travail de la
  pile**, qu'un `RefreshDatabase` reconstruit de zéro à chaque exécution. Le
  fichier d'amorçage aligne `$_SERVER` sur ce que déclare `phpunit.xml`.

`tests/Feature/DmeIntegrationTest.php` couvre les jointures que ni WorkFlow ni
le module ne peuvent vérifier seuls : la capacité d'accès, l'absence de seconde
authentification, la création du dossier au premier accès, le chemin des SMS et
le journal d'audit commun.

La suite du module se lance de son côté, depuis son propre dépôt :

```bash
composer test
```

---

## 8. Qui administre le module

Le module a trois écrans d'administration, tous sous `/dme` :

| Écran | URL | Permission |
|---|---|---|
| Paramètres, rôles et permissions | `/dme/parametres` | `settings.manage`, `roles.manage` |
| Comptes du DME | `/dme/utilisateurs` | `users.manage` |
| Journal d'audit médical | `/dme/audit` | `audit.view` |

C'est **l'administrateur de WorkFlow** qui les tient, et il entre dans le
module de droit : `User::canAccessDme()` répond oui pour le rôle `admin` sans
passer par la case à cocher. Ce n'est pas une faveur, c'est une nécessité — il
n'a aucun type de personnel, la case `can_access_dme` n'existe donc nulle part
pour lui dans `/admin`, et il serait le seul compte à ne jamais pouvoir
l'obtenir. Le module resterait sans administrateur.

Il a également l'action « Dossier medical complet » dans `/admin` → Patients,
au même titre qu'un médecin dans « Mes patients ». C'est une décision assumée :
un compte administratif accède ainsi à l'antécédent médical, aux ordonnances et
aux examens de chaque patient. Pour l'en écarter tout en le laissant
administrer, il faudrait réduire ses permissions DME à `users.manage`,
`settings.manage`, `roles.manage`, `audit.view` et `sms.view` dans
`DmePermissionSeeder`, et retirer le lien de `patient-directory.blade.php`.

### Retirer un dossier du DME

Le module distingue deux gestes, et l'écart entre eux est le sujet :

- **Archiver** (`patients.delete`) range un dossier : il sort de la liste des
  patients et n'est plus modifiable, mais reste entièrement consultable et se
  restaure. Rien n'est détruit.
- **Supprimer** (`patients.purge`) détruit le dossier et tout son contenu
  clinique. Le geste exige un dossier **déjà archivé**, le numéro retapé à
  l'identique et un motif. Seule l'entrée du journal d'audit survit.

`DmePermissionSeeder` ne donne `patients.purge` qu'à l'administrateur : un
médecin ne peut ni archiver ni supprimer.

**La suppression d'un dossier patient dans WorkFlow ne touche pas au DME** —
décision prise, pas oubli. Le dossier médical est une archive indépendante qui
survit à la file d'attente. Conséquence à connaître : sa liaison
`dme_patient_identifiers` devient orpheline, et un patient réenregistré sous un
nouveau `patient_code` recevra un second dossier médical. Le premier se
retrouve en filtrant la liste des patients du module sur « Archivé », ou par
son numéro.

Reste un point non tranché : les écrans de comptes et de rôles du module
agissent sur les mêmes comptes que `/admin` sans en connaître les règles —
type de personnel, rattachement au service, rôle cloisonné. Créer un compte
depuis `/dme/utilisateurs` produit un utilisateur que WorkFlow ne sait pas
placer.
