<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Keneya\Dme\Database\Seeders\RoleAndPermissionSeeder;
use Keneya\Dme\Database\Seeders\ServiceSeeder;
use Keneya\Dme\Database\Seeders\SmsTemplateSeeder;
use Keneya\Dme\Dme;
use Keneya\Dme\DmeServiceProvider;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Socle des tests du module.
 *
 * Les tests s'exécutent dans une application Laravel minimale fournie par
 * Orchestra Testbench : c'est exactement la situation visée, le module
 * monté chez un hôte, et non l'application autonome de la phase 1.
 *
 * Par défaut, le mode autonome de développement est **inactif** : le
 * module est testé tel qu'il tournera chez Keneya Workflow. L'autorisation
 * d'accès de haut niveau est accordée ici comme le ferait un hôte, par
 * une capacité ; les tests qui vérifient le refus la retirent.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * @param  \Illuminate\Foundation\Application  $app
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            \Spatie\Permission\PermissionServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
            \Barryvdh\DomPDF\ServiceProvider::class,
            \Laravel\Sanctum\SanctumServiceProvider::class,
            DmeServiceProvider::class,
        ];
    }

    /**
     * Configuration de l'application hôte de test.
     *
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        $config = $app['config'];

        $config->set('database.default', 'testing');
        $config->set('queue.default', 'sync');

        // L'hôte désigne le modèle utilisateur ; ici, l'annuaire du module.
        $config->set('auth.providers.users.model', User::class);

        // Module monté chez un hôte : pas de mode autonome.
        $config->set('dme.standalone.enabled', false);
        $config->set('dme.standalone.auto_login', false);

        // Aucun SMS réel n'est émis pendant les tests.
        $config->set('dme.sms.gateway', 'array');

        $config->set('dme.demo.enabled', true);
        $config->set('dme.demo.password', 'MotDePasseDeTest2026');
    }

    /**
     * Routes de l'application hôte.
     *
     * Un hôte a sa propre page de connexion : le module s'y repose et n'en
     * fournit aucune. La déclarer ici, c'est reproduire la situation
     * réelle, et vérifier qu'un visiteur non authentifié y est bien
     * renvoyé plutôt que dans le module.
     *
     * @param  \Illuminate\Routing\Router  $router
     */
    protected function defineRoutes($router): void
    {
        $router->get('/connexion-hote', static fn () => 'Connexion de l\'application hôte')
            ->name('login');
    }

    protected function setUp(): void
    {
        parent::setUp();

        Dme::flushState();

        // L'hôte accorde l'accès au module. Les tests de la porte d'entrée
        // redéfinissent cette capacité pour vérifier le refus.
        $this->grantHostAccess();

        // Les relations non chargées deviennent des erreurs : une requête
        // N+1 échoue en test plutôt que de se glisser en production (§58).
        Model::preventLazyLoading();
        Model::preventSilentlyDiscardingAttributes();
    }

    protected function tearDown(): void
    {
        Dme::flushState();
        Model::preventLazyLoading(false);
        Model::preventSilentlyDiscardingAttributes(false);

        parent::tearDown();
    }

    /**
     * Simule la décision d'accès de l'application hôte.
     */
    protected function grantHostAccess(bool $granted = true): void
    {
        Gate::define('dme.access', static fn () => $granted);
    }

    /**
     * Crée les rôles, permissions, services et modèles SMS nécessaires à
     * la quasi-totalité des tests. Appelé explicitement plutôt qu'en
     * setUp() afin qu'un test unitaire pur n'ait pas à payer ce coût.
     */
    protected function seedReferenceData(): void
    {
        $this->seed([
            RoleAndPermissionSeeder::class,
            ServiceSeeder::class,
            SmsTemplateSeeder::class,
        ]);
    }

    /**
     * Fabrique un utilisateur actif portant un rôle donné.
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function userWithRole(string $role = Rbac::ROLE_DOCTOR, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->assignRole($role);

        return $user->fresh();
    }
}
