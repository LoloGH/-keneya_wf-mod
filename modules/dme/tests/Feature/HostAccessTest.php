<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Keneya\Dme\Dme;
use Keneya\Dme\Models\AuditLog;
use Keneya\Dme\Support\Rbac;
use Keneya\Dme\Tests\TestCase;

/**
 * Porte d'entrée du module : l'accès est accordé par l'application hôte.
 *
 * La propriété vérifiée ici est celle qui compte le plus pour la phase 2 :
 * le module ne décide jamais seul qu'un utilisateur a le droit d'ouvrir un
 * dossier médical, même si cet utilisateur a par ailleurs toutes les
 * permissions internes du DME.
 */
class HostAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedReferenceData();
    }

    public function test_l_acces_est_ouvert_quand_l_hote_l_accorde(): void
    {
        $this->grantHostAccess(true);

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.dashboard'))
            ->assertOk();
    }

    public function test_l_acces_est_refuse_quand_l_hote_ne_l_accorde_pas(): void
    {
        $this->grantHostAccess(false);

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.dashboard'))
            ->assertForbidden();
    }

    public function test_un_administrateur_du_dme_reste_dehors_sans_accord_de_l_hote(): void
    {
        // Toutes les permissions internes du monde ne remplacent pas la
        // décision de l'hôte : c'est le cœur de la séparation.
        $this->grantHostAccess(false);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route('dme.patients.index'))
            ->assertForbidden();
    }

    public function test_sans_capacite_ni_attribut_ni_resolveur_l_acces_est_ferme(): void
    {
        // Aucune décision d'accès n'est configurée : un module monté sans
        // consigne explicite doit rester fermé, pas s'ouvrir par défaut.
        config()->set('dme.access.ability', null);
        config()->set('dme.access.attribute', null);

        $this->actingAs($this->userWithRole(Rbac::ROLE_ADMIN))
            ->get(route('dme.dashboard'))
            ->assertForbidden();
    }

    public function test_un_attribut_porte_par_l_utilisateur_suffit(): void
    {
        config()->set('dme.access.ability', 'capacite-inexistante');
        config()->set('dme.access.attribute', 'is_active');

        Gate::define('capacite-inexistante', static fn () => false);

        $this->actingAs($this->userWithRole(Rbac::ROLE_NURSE, ['is_active' => true]))
            ->get(route('dme.dashboard'))
            ->assertOk();
    }

    public function test_le_resolveur_de_l_hote_prime_sur_la_configuration(): void
    {
        // L'hôte accorde par capacité...
        $this->grantHostAccess(true);

        // ... mais son résolveur, s'il en déclare un, fait autorité.
        Dme::authorizeAccessUsing(static fn () => false);

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.dashboard'))
            ->assertForbidden();
    }

    public function test_un_refus_est_trace_dans_le_journal_d_audit(): void
    {
        $this->grantHostAccess(false);

        $this->actingAs($this->userWithRole(Rbac::ROLE_DOCTOR))
            ->get(route('dme.dashboard'))
            ->assertForbidden();

        $this->assertDatabaseHas('activity_log', [
            'action' => 'dme_access_denied',
            'outcome' => 'denied',
        ]);

        $this->assertNotNull(AuditLog::where('action', 'dme_access_denied')->first());
    }

    public function test_le_mode_autonome_de_developpement_est_inactif_par_defaut(): void
    {
        // Aucune route de connexion propre au module, et l'accès reste
        // suspendu à la décision de l'hôte : c'est l'état normal.
        $this->assertFalse(app(\Keneya\Dme\Standalone\StandaloneMode::class)->enabled());
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('dme.login'));
        $this->assertFalse(\Illuminate\Support\Facades\Route::has('dme.logout'));
    }

    public function test_un_visiteur_anonyme_est_renvoye_vers_l_authentification_de_l_hote(): void
    {
        // La porte ne renvoie pas 403 à un anonyme : c'est `auth` qui doit
        // le rediriger vers la connexion de l'hôte.
        $this->grantHostAccess(false);

        $this->get(route('dme.dashboard'))->assertRedirect(route('login'));
    }
}
