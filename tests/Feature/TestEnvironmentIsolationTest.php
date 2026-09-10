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
    /**
     * La suite ne pointe jamais une base de travail.
     *
     * Deux environnements, une seule regle. Sur un poste, phpunit.xml impose
     * SQLite en memoire. La CI, elle, valide la meme suite contre MariaDB —
     * c'est la porte de sortie `KENEYA_TEST_DB_FROM_ENV`, prevue et
     * documentee dans tests/bootstrap.php, parce que les deux moteurs ne se
     * comportent pas pareil et que l'hopital tourne sur MariaDB.
     *
     * Exiger SQLite sans condition revenait a interdire cette porte : c'est ce
     * qu'a fait la premiere version de ce test, et elle a fait echouer la CI
     * pendant deux commits. Ce qui doit tenir des deux cotes, c'est que la
     * base visee soit une base dediee aux tests.
     */
    public function test_la_suite_ne_pointe_jamais_une_base_de_travail(): void
    {
        $connexion = config('database.default');
        $base = (string) config("database.connections.$connexion.database");

        if (getenv('KENEYA_TEST_DB_FROM_ENV') === '1') {
            $this->assertStringContainsString(
                'test',
                $base,
                'Avec KENEYA_TEST_DB_FROM_ENV, la base visee doit etre dediee aux tests.',
            );

            return;
        }

        $this->assertSame('sqlite', $connexion, 'Sans la porte de sortie, la suite tourne sur SQLite.');
        $this->assertSame(':memory:', $base);
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
