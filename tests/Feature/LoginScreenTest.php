<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ecran de connexion : la refonte visuelle de la v3.2 ne doit rien changer au
 * contrat du formulaire ni aux protections qui l'entourent.
 */
class LoginScreenTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------- Le contrat du formulaire

    public function test_le_formulaire_poste_vers_la_route_de_connexion_avec_un_jeton(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('method="POST"', escape: false)
            ->assertSee('action="'.route('login.store').'"', escape: false)
            ->assertSee('name="_token"', escape: false);
    }

    public function test_les_trois_champs_attendus_par_le_serveur_sont_presents(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('name="email"', escape: false)
            ->assertSee('name="password"', escape: false)
            ->assertSee('name="remember"', escape: false);
    }

    /**
     * Le produit est destine a plusieurs etablissements : l'ecran de connexion
     * doit dire auquel on se connecte.
     */
    public function test_le_nom_de_l_etablissement_est_affiche(): void
    {
        $this->get('/connexion')
            ->assertOk()
            ->assertSee(hospital_name());
    }

    // ----------------------------------------------------- La scene et la carte

    public function test_la_scene_accompagne_la_carte_sans_masquer_le_formulaire(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('login-stage__scene', escape: false)
            ->assertSee('login-card', escape: false)
            ->assertSee('Bienvenue', escape: false);

        // La scene est une photographie posee en fond : elle n'apporte aucun
        // texte au document. Le nom du produit reste accessible par
        // l'alternative du bloc de marque, seule image porteuse de sens.
        $this->assertStringContainsString(
            'alt="'.config('keneya.name').' - Espace professionnel"',
            $response->getContent(),
        );
    }

    public function test_la_mention_de_droits_pointe_vers_le_site_de_l_editeur(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('Tous droits reserves', escape: false)
            ->assertSee(date('Y'))
            ->assertSee('href="https://sukaxess.com"', escape: false)
            ->assertSee('>AXESs</a>', escape: false);
    }

    /**
     * Sur telephone, la rangee des quatre atouts ferme la carte et la mention
     * de l'editeur vient sous elle. Aucune regle de style ne les reordonne :
     * c'est l'ordre du document qui le dit, et c'est donc lui qu'on verifie.
     */
    public function test_la_mention_de_droits_vient_apres_la_rangee_des_atouts(): void
    {
        $contenu = $this->get('/connexion')->assertOk()->getContent();

        $atouts = strpos($contenu, 'login-atouts');
        $droits = strpos($contenu, 'login-card__rights');

        $this->assertNotFalse($atouts);
        $this->assertNotFalse($droits);
        $this->assertGreaterThan($atouts, $droits);
    }

    // --------------------------------------------------------- Les protections

    public function test_le_message_du_serveur_est_repris_dans_la_carte(): void
    {
        $this->from('/connexion')
            ->post(route('login.store'), [
                'email' => 'inconnu@keneya.local',
                'password' => 'mauvais-mot-de-passe',
            ])
            ->assertRedirect('/connexion')
            ->assertSessionHasErrors('email');

        $this->followingRedirects()
            ->get('/connexion')
            ->assertSee('Ces identifiants ne correspondent a aucun compte.', escape: false);
    }

    public function test_la_limitation_des_tentatives_reste_active(): void
    {
        for ($essai = 0; $essai < 6; $essai++) {
            $this->post(route('login.store'), [
                'email' => 'admin@keneya.local',
                'password' => 'mauvais-mot-de-passe',
            ]);
        }

        $this->post(route('login.store'), [
            'email' => 'admin@keneya.local',
            'password' => 'mauvais-mot-de-passe',
        ])->assertSessionHasErrorsIn('default', ['email']);

        $this->assertStringContainsString(
            'Trop de tentatives de connexion.',
            (string) session('errors')->first('email'),
        );
    }

    public function test_un_compte_reel_se_connecte_et_repart_vers_son_interface(): void
    {
        $admin = $this->makeAdmin();
        $admin->update(['password' => 'mot-de-passe-de-test']);

        $this->post(route('login.store'), [
            'email' => $admin->email,
            'password' => 'mot-de-passe-de-test',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($admin);
    }
}
