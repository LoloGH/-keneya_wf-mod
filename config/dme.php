<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Roles;

/*
|--------------------------------------------------------------------------
| Configuration du module Keneya-DME
|--------------------------------------------------------------------------
|
| Fichier publiable du module (php artisan vendor:publish --tag=dme-config).
| Il regroupe tout ce que l'application hôte peut avoir besoin d'ajuster :
| montage des routes, autorisation d'accès accordée par l'hôte, envoi de
| SMS, valeurs métier et seuils cliniques.
|
| Aucune valeur métier ne doit être codée en dur ailleurs dans le module.
|
*/

return [

    /*
    | Version fonctionnelle du module (§63). Affichée dans l'interface, dans
    | les documents générés, et ajoutée aux URL des ressources statiques
    | pour invalider le cache du navigateur à chaque mise à jour.
    */
    'version' => '0.2.0',

    /*
    | Établissement de santé exploitant l'application. Ces valeurs sont
    | utilisées dans l'entête de l'interface et dans les documents PDF.
    */
    'facility' => [
        'name' => env('KENEYA_FACILITY_NAME', 'Centre Hospitalier Keneya'),
        'address' => env('KENEYA_FACILITY_ADDRESS', 'Bamako, Mali'),
        'phone' => env('KENEYA_FACILITY_PHONE', '+223 20 00 00 00'),
        'email' => env('KENEYA_FACILITY_EMAIL', 'contact@keneya.test'),
    ],

    /*
    | Préfixes des identifiants métier lisibles (§37 de la spécification).
    | Format généré : <PREFIXE>-<ANNEE>-<SEQUENCE SUR 6 CHIFFRES>.
    | Ces identifiants sont stables : ils ne sont jamais recalculés après
    | création et servent de clé de correspondance lors d'une future
    | intégration FHIR / HL7.
    */
    'identifiers' => [
        'padding' => 6,
        'prefixes' => [
            'patient' => 'PAT',
            'consultation' => 'CONS',
            'prescription' => 'ORD',
            'lab_order' => 'LAB',
            'imaging_order' => 'IMG',
            'hospitalization' => 'HOSP',
            'document' => 'DOC',
            'appointment' => 'RDV',
            'care_order' => 'SOIN',
        ],
    ],

    /*
    | Pagination par défaut des listes (§58 — performance).
    */
    'pagination' => [
        'default' => 15,
        'timeline' => 20,
    ],

    /*
    | Stockage des documents médicaux. Le disque doit rester privé :
    | aucun document n'est servi directement depuis le système de fichiers,
    | tout téléchargement passe par une route contrôlée (§42).
    */
    'documents' => [
        // Le meme disque que les pieces jointes de WorkFlow. Les deux gardent
        // leurs tables — l'operationnel et le dossier medical ne sont ni le
        // meme objet ni les memes droits — mais les fichiers n'ont aucune
        // raison d'etre stockes deux fois, ni sauvegardes deux fois.
        'disk' => env('DME_DOCUMENTS_DISK', 'local'),
        'directory' => 'medical-documents',
        'max_size_kb' => 20480,
        'allowed_mimes' => ['pdf', 'jpg', 'jpeg', 'png', 'dcm', 'txt'],
    ],

    /*
    | Seuils cliniques utilisés pour signaler une constante hors norme.
    | Purement indicatif : l'application n'établit aucun diagnostic.
    */
    'vitals' => [
        'temperature' => ['min' => 36.0, 'max' => 37.5],
        'heart_rate' => ['min' => 60, 'max' => 100],
        'respiratory_rate' => ['min' => 12, 'max' => 20],
        'oxygen_saturation' => ['min' => 95, 'max' => 100],
        'systolic' => ['min' => 90, 'max' => 139],
        'diastolic' => ['min' => 60, 'max' => 89],
        'glycemia' => ['min' => 0.7, 'max' => 1.1],
    ],

    /*
    | Données de démonstration. Le seeder de démonstration refuse de
    | s'exécuter en dehors des environnements local et testing.
    */
    'demo' => [
        'enabled' => env('DEMO_SEED_ENABLED', false),
        'password' => env('DEMO_USER_PASSWORD'),
    ],

    /*
    |----------------------------------------------------------------------
    | Montage du module dans l'application hôte
    |----------------------------------------------------------------------
    |
    | Le module s'expose sous un préfixe d'URL et un préfixe de nom de
    | route qui lui sont propres, afin de ne jamais entrer en collision
    | avec les routes de l'hôte. Les intergiciels listés ici s'appliquent
    | à toutes les routes web du module : `dme.access` est celui qui
    | vérifie l'autorisation de haut niveau accordée par l'hôte.
    */
    /*
    |----------------------------------------------------------------------
    | Ressources statiques
    |----------------------------------------------------------------------
    |
    | Chemin, sous le répertoire public de l'hôte, où sont copiées les
    | feuilles de style, les scripts et les images du module :
    |
    |   php artisan vendor:publish --tag=dme-assets --force
    */
    'assets' => [
        'path' => env('DME_ASSETS_PATH', 'vendor/dme'),
    ],

    'route' => [
        'prefix' => env('DME_ROUTE_PREFIX', 'dme'),
        'name' => 'dme.',
        'middleware' => ['web', 'dme.access'],
        'api' => [
            'prefix' => env('DME_API_ROUTE_PREFIX', 'dme/api'),
            'middleware' => ['api'],
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Modèles de l'application hôte
    |----------------------------------------------------------------------
    |
    | Le module partage la table `users` avec son hôte : c'est le même
    | compte, la même session, la même ligne en base. Le modèle qui la
    | représente doit donc être celui de l'hôte — celui que `Auth::user()`
    | renvoie — sinon le module manipulerait, pour une même personne, des
    | objets d'une autre classe que ceux de la session en cours.
    |
    | Ce modèle doit satisfaire Keneya\Dme\Contracts\DmeUser, ce que le
    | trait Keneya\Dme\Models\Concerns\IsDmePractitioner suffit à lui
    | apporter.
    |
    | Laissé nul, le module utilise son propre modèle, qui convient quand
    | il tourne seul.
    */
    'models' => [
        // Le compte connecte est celui de WorkFlow : meme table `users`, meme
        // session, meme ligne. Le trait IsDmePractitioner, ajoute a ce modele,
        // lui apporte les relations et les reponses attendues par le module.
        'user' => User::class,
    ],

    /*
    |----------------------------------------------------------------------
    | Correspondance des roles avec ceux de WorkFlow
    |----------------------------------------------------------------------
    |
    | Le module raisonne en metiers (medecin, infirmier, laboratoire),
    | WorkFlow en interfaces (admin, doctor, receptionist, cashier). Ce
    | tableau traduit l'un dans l'autre partout ou le module cherche des
    | praticiens : la liste des medecins d'un rendez-vous, le medecin
    | traitant d'un dossier, les soignants a qui confier un soin.
    |
    | Les roles du module qui n'ont pas d'equivalent parmi les quatre roles
    | fixes de WorkFlow — infirmier, laboratoire, radiologie, pharmacien —
    | ne sont pas declares : ils relevent des types de personnel generiques,
    | qui ne portent aucun role Spatie. Le module rend alors une liste vide,
    | et non une erreur.
    */
    'roles' => [
        'administrateur' => Roles::ADMIN,
        'medecin' => Roles::DOCTOR,
        'reception' => Roles::RECEPTIONIST,
    ],

    /*
    |----------------------------------------------------------------------
    | Autorisation d'accès de haut niveau (décidée par l'hôte)
    |----------------------------------------------------------------------
    |
    | Le module ne décide jamais seul qui a le droit d'ouvrir le dossier
    | médical : il vérifie que l'application hôte le lui a accordé. Trois
    | formes d'accord sont acceptées, dans cet ordre :
    |
    |   1. un résolveur enregistré par l'hôte : Dme::authorizeAccessUsing(...)
    |   2. une capacité (Gate / permission) portée par l'utilisateur
    |   3. un attribut booléen porté par l'utilisateur authentifié
    |
    | Si aucune de ces formes n'accorde l'accès, il est refusé. C'est
    | volontaire : un module monté sans décision explicite de l'hôte doit
    | rester fermé.
    */
    'access' => [
        'ability' => env('DME_ACCESS_ABILITY', 'dme.access'),
        'attribute' => env('DME_ACCESS_ATTRIBUTE', 'can_access_dme'),
        'guard' => env('DME_AUTH_GUARD'),
    ],

    /*
    |----------------------------------------------------------------------
    | Mode autonome de développement
    |----------------------------------------------------------------------
    |
    | Réservé au développement et aux tests : il fabrique une session
    | authentifiée, ouvre une page de connexion locale et considère
    | l'autorisation de l'hôte comme toujours accordée, afin que le module
    | reste navigable et vérifiable avant que Keneya Workflow ne soit
    | branché.
    |
    | Ce mode est inactif par défaut et refuse de s'activer en production,
    | quelle que soit la valeur de la variable d'environnement.
    */
    'standalone' => [
        'enabled' => (bool) env('DME_STANDALONE_DEV', false),
        // Utilisateur ouvert automatiquement, sans mot de passe, quand
        // `auto_login` est actif (navigation manuelle sans compte).
        'auto_login' => (bool) env('DME_STANDALONE_AUTO_LOGIN', false),
        'user' => [
            'email' => env('DME_STANDALONE_USER_EMAIL', 'dev@keneya.test'),
            'name' => env('DME_STANDALONE_USER_NAME', 'Praticien de développement'),
            'role' => env('DME_STANDALONE_USER_ROLE', 'administrateur'),
        ],
    ],

    /*
    |----------------------------------------------------------------------
    | Envoi de SMS
    |----------------------------------------------------------------------
    |
    | Le module ne dépend que de SmsDispatcherContract. `dispatcher`
    | désigne l'implémentation liée au contrat lorsque l'hôte n'en fournit
    | aucune :
    |
    |   queued : file d'attente interne du module (persistance, passerelle,
    |            historique) — comportement autonome historique ;
    |   log    : journalise le message sans jamais l'émettre.
    |
    | Monté dans Keneya Workflow, l'hôte liera sa propre implémentation au
    | contrat et cette valeur ne sera plus consultée.
    */
    'sms' => [

        'dispatcher' => env('DME_SMS_DISPATCHER', 'queued'),

        /*
        | Passerelle active.
        |
        | `?:` et non un défaut d'env : Laravel convertit la chaîne « null »
        | en PHP null, ce qui laisserait la passerelle non résolue.
        */
        'gateway' => env('SMS_GATEWAY') ?: 'log',

        'sender' => env('SMS_SENDER', 'Keneya'),

        /*
        | File d'attente : les SMS y transitent toujours, afin que
        | l'indisponibilité d'une passerelle ne bloque jamais un acte médical.
        */
        'queue' => [
            'connection' => env('SMS_QUEUE_CONNECTION'),
            'name' => env('SMS_QUEUE', 'sms'),
        ],

        /*
        | Politique de réessai en cas d'échec de la passerelle.
        */
        'retry' => [
            'max_attempts' => (int) env('SMS_MAX_ATTEMPTS', 3),
            'delay_seconds' => (int) env('SMS_RETRY_DELAY', 60),
        ],

        /*
        | Suivi d'acheminement : SMSGate accuse d'abord réception du message
        | (« accepté »), puis notifie son envoi et sa remise. La commande
        | `keneya:sms:refresh` interroge la passerelle pour les messages
        | encore en transit.
        */
        'status_tracking' => [
            'enabled' => (bool) env('SMS_STATUS_TRACKING', true),
            // Fenêtre au-delà de laquelle un message non finalisé n'est plus interrogé.
            'max_age_hours' => (int) env('SMS_STATUS_MAX_AGE_HOURS', 48),
            'batch_size' => (int) env('SMS_STATUS_BATCH_SIZE', 100),
        ],

        'gateways' => [

            /*
            | SMSGate — passerelle de production.
            |
            | Deux modes d'exploitation, tous deux couverts par cette
            | configuration :
            |   - cloud   : https://api.sms-gate.app/3rdparty/v1
            |   - local   : http://<ip-de-l-appareil>:8080/3rdparty/v1
            |
            | Authentification HTTP Basic (identifiant + mot de passe) ou
            | Bearer si seul un jeton est fourni. Aucun secret n'est stocké
            | dans le dépôt : tout provient de l'environnement.
            */
            'smsgate' => [
                'driver' => 'smsgate',
                'base_url' => env('SMSGATE_BASE_URL', 'https://api.sms-gate.app/3rdparty/v1'),
                'username' => env('SMSGATE_USERNAME'),
                'password' => env('SMSGATE_PASSWORD'),
                'token' => env('SMSGATE_TOKEN'),
                'sender' => env('SMSGATE_SENDER'),
                // Carte SIM à utiliser sur l'appareil (null = choix de l'appareil).
                'sim_number' => env('SMSGATE_SIM_NUMBER') ? (int) env('SMSGATE_SIM_NUMBER') : null,
                'with_delivery_report' => (bool) env('SMSGATE_DELIVERY_REPORT', true),
                'timeout' => (int) env('SMSGATE_TIMEOUT', 15),
                // Durée de validité du message côté passerelle, en secondes.
                'ttl' => env('SMSGATE_TTL') ? (int) env('SMSGATE_TTL') : null,
                'verify_tls' => (bool) env('SMSGATE_VERIFY_TLS', true),
            ],

            /*
            | Développement : aucun envoi réel, le message est journalisé.
            | L'interface signale explicitement qu'il s'agit d'une simulation.
            */
            'log' => [
                'driver' => 'log',
                'channel' => env('SMS_LOG_CHANNEL'),
            ],

            /*
            | Tests automatisés : accepte tout, n'émet rien.
            */
            'array' => [
                'driver' => 'array',
            ],
        ],

        /*
        | Indicatif appliqué aux numéros saisis au format national.
        */
        'default_country_code' => env('SMS_COUNTRY_CODE', '+223'),
    ],

];
