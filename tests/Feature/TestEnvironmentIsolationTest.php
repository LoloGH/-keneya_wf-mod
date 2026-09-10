<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * La suite ne touche jamais une vraie base (v3.3.2).
 *
 * `tests/bootstrap.php` remettait deja phpunit.xml en position d'autorite face
 * a l'environnement injecte par docker-compose. Un second chemin restait
 * ouvert, et il etait pire : `bootstrap/cache/config.php`.
 *
 * Ce fichier est un instantane fige de la configuration de la pile de travail.
 * Quand il existe, Laravel le charge tel quel et ne consulte plus
 * l'environnement du tout — les <env> de phpunit.xml deviennent sans effet, la
 * suite se retrouve sur `mysql` / `keneya_wf_mod`, et le premier test venu
 * reconstruit cette base de zero, puisque RefreshDatabase execute
 * `migrate:fresh`.
 *
 * Ce n'est pas une hypothese : la pile de developpement construit ce cache a
 * chaque demarrage du conteneur depuis la v3.3.2, parce qu'il divise par deux
 * le temps de reponse. La sonde qui a servi a le constater rendait bien
 * « connexion = mysql | base = keneya_wf_mod | env = production ».
 *
 * D'ou ces trois verifications. Elles ne coutent rien et se declencheraient au
 * premier retour en arriere.
 */
class TestEnvironmentIsolationTest extends TestCase
{
    public function test_la_suite_tourne_sur_sqlite_en_memoire(): void
    {
        $connexion = config('database.default');

        $this->assertSame('sqlite', $connexion, 'La suite doit tourner sur SQLite, jamais sur la base de la pile.');
        $this->assertSame(':memory:', config("database.connections.$connexion.database"));
    }

    public function test_l_environnement_est_celui_des_tests(): void
    {
        $this->assertSame('testing', config('app.env'));
    }

    /**
     * La verification qui compte : elle tient meme si un cache de
     * configuration est present, ce qui est le cas normal sur un poste de
     * developpement.
     */
    public function test_le_cache_de_configuration_de_la_pile_est_ecarte(): void
    {
        $chemin = $this->app->getCachedConfigPath();

        $this->assertStringNotContainsString(
            'bootstrap'.DIRECTORY_SEPARATOR.'cache'.DIRECTORY_SEPARATOR.'config.php',
            str_replace('/', DIRECTORY_SEPARATOR, $chemin),
            'La suite doit lire la configuration reelle, pas l\'instantane de la pile.',
        );

        $this->assertFileDoesNotExist($chemin);
    }
}
