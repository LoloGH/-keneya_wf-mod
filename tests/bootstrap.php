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
| reconstruisait de zero — RefreshDatabase execute `migrate:fresh`.
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
