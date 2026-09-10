<?php

/*
|--------------------------------------------------------------------------
| Amorcage de la suite de tests
|--------------------------------------------------------------------------
|
| Les <env> de phpunit.xml ne suffisent pas sous Docker, et l'ecart est
| couteux : docker-compose injecte le `.env` de l'application dans
| l'environnement du conteneur (`env_file`), PHP le recopie dans $_SERVER, et
| c'est $_SERVER que Laravel consulte EN PREMIER. PHPUnit, lui, n'ecrit que
| dans putenv() et $_ENV. Resultat : `DB_CONNECTION=mysql` l'emportait, la
| suite tournait sur la base de travail de la pile, et le premier test venu la
| reconstruisait de zero, RefreshDatabase execute `migrate:fresh`.
|
| Ce fichier remet phpunit.xml en position d'autorite, jusque dans $_SERVER.
|
| Une exception, explicite : `KENEYA_TEST_DB_FROM_ENV=1` laisse les variables
| DB_* de l'environnement passer. C'est ce dont la CI a besoin pour valider la
| meme suite contre MariaDB, le moteur reellement utilise a l'hopital. Il faut
| le demander : rien ne doit pouvoir diriger les tests vers une vraie base
| par accident.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
| Les caches de demarrage ne doivent jamais servir aux tests.
|
| `bootstrap/cache/config.php` est un instantane fige de la configuration de
| la pile de travail. Quand il existe, Laravel le charge tel quel et ne
| consulte plus l'environnement : tout le soin pris ci-dessus a remettre
| phpunit.xml en autorite devient sans effet, et la suite se retrouve sur
| `mysql` / `keneya_wf_mod` au lieu du SQLite en memoire. Le premier test
| venu la reconstruit alors de zero — RefreshDatabase execute
| `migrate:fresh`.
|
| Ce n'est pas une hypothese : la pile de developpement construit ces caches
| a chaque demarrage du conteneur depuis la v3.3.2, parce qu'ils divisent par
| deux le temps de reponse.
|
| Laravel laisse choisir ou il va les chercher. On les envoie vers un chemin
| qui n'existe pas : la suite lit donc toujours la configuration reelle.
*/
foreach ([
    // Ces trois-la seulement : ce sont des instantanes de la configuration et
    // du code de la pile. Les deux autres — `APP_PACKAGES_CACHE` et
    // `APP_SERVICES_CACHE` — sont des caches de decouverte de paquets, que
    // Laravel reconstruit et doit donc pouvoir ecrire ; les detourner vers un
    // chemin absent fait echouer l'amorcage.
    'APP_CONFIG_CACHE', 'APP_ROUTES_CACHE', 'APP_EVENTS_CACHE',
] as $cache) {
    $chemin = __DIR__.'/../bootstrap/cache/tests-sans-cache/'.strtolower($cache).'.php';

    putenv($cache.'='.$chemin);
    $_ENV[$cache] = $_SERVER[$cache] = $chemin;
}

$variablesBase = ['DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_URL'];
$baseDepuisEnvironnement = getenv('KENEYA_TEST_DB_FROM_ENV') === '1';

foreach ($_ENV as $cle => $valeur) {
    if ($baseDepuisEnvironnement && in_array($cle, $variablesBase, true)) {
        continue;
    }

    $_SERVER[$cle] = $valeur;
}

// Selon les versions, PHPUnit peut appliquer ses <env> apres ce fichier : on
// aligne aussi $_SERVER sur putenv(), qui est ecrit dans tous les cas.
foreach ([
    'APP_ENV', 'APP_MAINTENANCE_DRIVER', 'BCRYPT_ROUNDS', 'BROADCAST_CONNECTION',
    'CACHE_STORE', 'DB_CONNECTION', 'DB_DATABASE', 'DB_URL', 'MAIL_MAILER',
    'QUEUE_CONNECTION', 'SESSION_DRIVER', 'SMSGATE_ENABLED', 'SMSGATE_URL',
] as $cle) {
    if ($baseDepuisEnvironnement && in_array($cle, $variablesBase, true)) {
        continue;
    }

    $valeur = getenv($cle);

    if ($valeur !== false) {
        $_ENV[$cle] = $_SERVER[$cle] = $valeur;
    }
}
