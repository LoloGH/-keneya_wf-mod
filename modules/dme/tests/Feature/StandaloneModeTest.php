<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Keneya\Dme\Standalone\StandaloneMode;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Mode autonome de développement.
 *
 * Il n'existe que pour pouvoir naviguer dans le module tant que Keneya
 * Workflow ne le porte pas. Les propriétés vérifiées ici sont autant des
 * garanties de sécurité que de confort : ce mode doit fonctionner quand
 * on le demande, et rester rigoureusement inactif sinon.
 */
class StandaloneModeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  \Illuminate\Foundation\Application  $app
     */
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('dme.standalone.enabled', true);

        // Aucune décision d'accès n'est configurée : c'est bien le mode
        // autonome, et lui seul, qui doit ouvrir la porte.
        $app['config']->set('dme.access.ability', null);
        $app['config']->set('dme.access.attribute', null);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_la_page_de_connexion_locale_existe(): void
    {
        $this->assertTrue(Route::has('dme.login'));

        $this->get(route('dme.login'))
            ->assertOk()
            ->assertSee('Connexion');
    }

    public function test_l_acces_est_accorde_sans_decision_d_un_hote(): void
    {
        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.dashboard'))
            ->assertOk();
    }

    public function test_un_compte_desactive_ne_peut_pas_se_connecter(): void
    {
        $user = $this->userWithRole(Rbac::ROLE_DOCTOR, ['is_active' => false]);

        $this->post(route('dme.login'), [
            'email' => $user->email,
            'password' => 'MotDePasseDeTest2026',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_le_mode_autonome_est_actif_ici(): void
    {
        $this->assertTrue(app(StandaloneMode::class)->enabled());
    }

    public function test_il_ne_s_active_jamais_en_production(): void
    {
        // Même explicitement demandé, il est refusé en production : c'est
        // le garde-fou qui rend impossible une authentification maison sur
        // une installation réelle.
        $this->app->detectEnvironment(static fn () => 'production');

        $standalone = new StandaloneMode($this->app, $this->app['config']);

        $this->assertFalse($standalone->enabled());
        $this->assertTrue($standalone->refusedInProduction());
    }
}
