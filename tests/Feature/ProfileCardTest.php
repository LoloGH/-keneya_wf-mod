<?php

namespace Tests\Feature;

use App\Livewire\Shared\ProfileCard;
use App\Models\Service;
use App\Support\Audit;
use App\Support\Roles;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Carte de profil et changement de mot de passe (v3.2.3, point 3).
 */
class ProfileCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_la_carte_montre_qui_l_on_est(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $doctor->user->update(['name' => 'Amadou Cisse']);

        Livewire::actingAs($doctor->user->refresh())
            ->test(ProfileCard::class)
            ->call('toggle')
            ->assertSee('Amadou Cisse')
            ->assertSee('Medecin')
            ->assertSee('Medecine Generale')
            ->assertSee($doctor->user->email);
    }

    public function test_l_avatar_porte_les_initiales(): void
    {
        $user = $this->makeAdmin();
        $user->update(['name' => 'Fatoumata Sidibe']);

        Livewire::actingAs($user->refresh())
            ->test(ProfileCard::class)
            ->assertSee('FS');
    }

    public function test_le_service_ne_s_affiche_pas_pour_qui_n_en_a_pas(): void
    {
        // Une receptionniste n'est rattachee a aucun service : une ligne
        // « Service : — » n'apprendrait rien.
        Livewire::actingAs($this->makeReceptionist())
            ->test(ProfileCard::class)
            ->call('toggle')
            ->assertSee('Receptionniste')
            ->assertDontSee('Service');
    }

    public function test_la_carte_met_la_fonction_a_cote_du_nom(): void
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $doctor->user->update(['name' => 'Amadou Cisse']);

        // La fonction est ce qu'on lit en premier : elle monte dans l'en-tete
        // plutot que de figurer comme une ligne de liste parmi d'autres.
        $rendu = Livewire::actingAs($doctor->user->refresh())
            ->test(ProfileCard::class)
            ->call('toggle')
            ->html();

        $this->assertStringContainsString('profil__head', $rendu);
        $this->assertStringContainsString('profil__role', $rendu);
        $this->assertStringNotContainsString('<dt>Fonction</dt>', $rendu);
    }

    // ------------------------------------------- Changement de mot de passe

    public function test_un_mot_de_passe_actuel_incorrect_est_refuse(): void
    {
        $user = $this->makeAdmin();
        $hash = $user->password;

        Livewire::actingAs($user)
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'ce-n-est-pas-le-bon')
            ->set('password', 'nouveaumotdepasse1')
            ->set('password_confirmation', 'nouveaumotdepasse1')
            ->call('changePassword')
            ->assertHasErrors('current_password');

        $this->assertSame($hash, $user->refresh()->password);
    }

    public function test_le_nouveau_mot_de_passe_doit_etre_confirme(): void
    {
        $user = $this->makeAdmin();

        Livewire::actingAs($user)
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'nouveaumotdepasse1')
            ->set('password_confirmation', 'autre-chose-1')
            ->call('changePassword')
            ->assertHasErrors('password');
    }

    public function test_un_mot_de_passe_trop_simple_est_refuse(): void
    {
        $user = $this->makeAdmin();

        Livewire::actingAs($user)
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'abcdefgh')
            ->set('password_confirmation', 'abcdefgh')
            ->call('changePassword')
            ->assertHasErrors('password');

        $this->assertTrue(Hash::check('motdepasse', $user->refresh()->password));
    }

    public function test_les_refus_de_complexite_sont_lisibles_en_francais(): void
    {
        $user = $this->makeAdmin();

        $composant = Livewire::actingAs($user)
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'abcdefgh')
            ->set('password_confirmation', 'abcdefgh')
            ->call('changePassword');

        $message = $composant->errors()->first('password');

        // Sans les traductions, l'agent lit « validation.password.numbers » :
        // une cle brute, qui ne lui dit pas ce qu'on attend de lui.
        $this->assertStringNotContainsString('validation.', $message);
        $this->assertStringContainsString('chiffre', $message);
    }

    public function test_le_changement_reussi_remplace_le_hash_et_est_journalise(): void
    {
        $user = $this->makeAdmin();
        $user->update(['name' => 'Awa Traore']);
        $ancien = $user->password;

        Livewire::actingAs($user->refresh())
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'nouveaumotdepasse1')
            ->set('password_confirmation', 'nouveaumotdepasse1')
            ->call('changePassword')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotSame($ancien, $user->password);
        $this->assertTrue(Hash::check('nouveaumotdepasse1', $user->password));

        $trace = Activity::where('event', Audit::EVENT_PASSWORD_CHANGED)->latest('id')->firstOrFail();

        $this->assertStringContainsString('Awa Traore', $trace->description);

        // Ni l'ancien ni le nouveau mot de passe ne doivent apparaitre au
        // journal, sous quelque forme que ce soit.
        $journal = json_encode([$trace->description, $trace->properties]);
        $this->assertStringNotContainsString('nouveaumotdepasse1', $journal);
        $this->assertStringNotContainsString('password', strtolower($journal));
        $this->assertStringNotContainsString(substr($ancien, 0, 20), $journal);
    }

    public function test_le_changement_ferme_les_autres_sessions(): void
    {
        $user = $this->makeAdmin();

        $composant = Livewire::actingAs($user)->test(ProfileCard::class);

        $ancien = $user->password;

        $composant
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'nouveaumotdepasse1')
            ->set('password_confirmation', 'nouveaumotdepasse1')
            ->call('changePassword')
            ->assertHasNoErrors();

        // C'est le hash en base qui fait tomber les autres sessions : le
        // marqueur qu'elles ont garde ne lui correspond plus. La preuve du
        // rejet effectif est faite plus bas, au niveau HTTP, la ou le
        // middleware tourne vraiment.
        $this->assertNotSame($ancien, $user->refresh()->password);

        // La session courante, elle, survit.
        $this->assertAuthenticatedAs($user);
    }

    public function test_le_middleware_qui_ferme_les_autres_sessions_est_actif(): void
    {
        // Sans AuthenticateSession dans le groupe `web`, logoutOtherDevices ne
        // ferme rien : il reecrit un marqueur que personne ne lit. Ce test
        // garde le middleware en place.
        $middlewares = app(Kernel::class)
            ->getMiddlewareGroups()['web'] ?? [];

        $this->assertContains(AuthenticateSession::class, $middlewares);
    }

    public function test_une_autre_session_du_meme_compte_est_bien_rejetee(): void
    {
        $user = $this->makeAdmin();
        $user->syncRoles([Roles::ADMIN]);

        // Session A : elle reste sur l'ancien hash.
        $this->actingAs($user)->get('/admin')->assertOk();
        $ancienMarqueur = session('password_hash_web');

        // Ailleurs, le mot de passe change.
        $user->forceFill(['password' => 'nouveaumotdepasse1'])->save();

        // La session A repasse avec son marqueur perime : elle tombe.
        session(['password_hash_web' => $ancienMarqueur]);

        $this->actingAs($user)->get('/admin')->assertRedirect(route('login'));
    }

    public function test_le_formulaire_se_replie_apres_un_changement_reussi(): void
    {
        $user = $this->makeAdmin();

        Livewire::actingAs($user)
            ->test(ProfileCard::class)
            ->call('startPasswordChange')
            ->set('current_password', 'motdepasse')
            ->set('password', 'nouveaumotdepasse1')
            ->set('password_confirmation', 'nouveaumotdepasse1')
            ->call('changePassword')
            ->assertSet('changingPassword', false)
            ->assertSet('password', '')
            ->assertSet('current_password', '');
    }
}
