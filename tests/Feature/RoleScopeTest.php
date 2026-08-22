<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Point 8 du scenario d'acceptation : cloisonnement strict des interfaces.
 * Un utilisateur qui tape a la main l'URL d'une autre interface est renvoye
 * vers la sienne, sans jamais voir le contenu de l'autre.
 */
class RoleScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_une_receptionniste_atteint_sa_propre_interface(): void
    {
        $this->actingAs($this->makeReceptionist())
            ->get('/reception')
            ->assertOk();
    }

    public function test_une_receptionniste_est_redirigee_depuis_admin(): void
    {
        $response = $this->actingAs($this->makeReceptionist())->get('/admin');

        $response->assertRedirect(route('reception.home'));
        $response->assertSessionHas('error');
    }

    public function test_une_receptionniste_est_redirigee_depuis_service(): void
    {
        $this->actingAs($this->makeReceptionist())
            ->get('/service')
            ->assertRedirect(route('reception.home'));
    }

    public function test_un_medecin_est_redirige_depuis_admin_et_reception(): void
    {
        $doctor = $this->makeDoctor(Service::factory()->create());

        $this->actingAs($doctor->user)->get('/admin')->assertRedirect(route('service.home'));
        $this->actingAs($doctor->user)->get('/reception')->assertRedirect(route('service.home'));
        $this->actingAs($doctor->user)->get('/service')->assertOk();
    }

    public function test_un_admin_est_redirige_depuis_reception_et_service(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get('/reception')->assertRedirect(route('admin.home'));
        $this->actingAs($admin)->get('/service')->assertRedirect(route('admin.home'));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_un_visiteur_non_authentifie_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/admin')->assertRedirect(route('login'));
        $this->get('/reception')->assertRedirect(route('login'));
        $this->get('/service')->assertRedirect(route('login'));
    }

    public function test_la_connexion_redirige_vers_l_interface_du_role(): void
    {
        $receptionist = $this->makeReceptionist();

        $this->post(route('login.store'), [
            'email' => $receptionist->email,
            'password' => 'motdepasse',
        ])->assertRedirect(route('home'));

        $this->actingAs($receptionist)->get(route('home'))->assertRedirect(route('reception.home'));
    }

    public function test_l_ecran_de_salle_d_attente_reste_public(): void
    {
        // /board est un affichage public, pas une interface de role.
        $this->get('/board')->assertOk();
    }
}
