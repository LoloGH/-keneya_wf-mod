<?php

namespace Tests\Feature;

use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * Identite visuelle : logo en SVG inline, ses deux variantes, et le favicon.
 */
class BrandLogoTest extends TestCase
{
    use RefreshDatabase;

    private function logo(array $props = []): string
    {
        return view('components.brand-logo', array_merge([
            'variant' => 'color',
            'lockup' => false,
            'title' => null,
            'attributes' => new ComponentAttributeBag([]),
        ], $props))->render();
    }

    // ------------------------------------------------------- Les variantes

    public function test_la_variante_couleur_utilise_le_bleu_nuit_et_le_vert(): void
    {
        $svg = $this->logo();

        $this->assertStringContainsString('#1D3A5C', $svg);
        $this->assertStringContainsString('#16A075', $svg);
        $this->assertStringNotContainsString('#FFFFFF', $svg);
    }

    public function test_la_variante_claire_remplace_l_encre_par_du_blanc(): void
    {
        $svg = $this->logo(['variant' => 'light']);

        $this->assertStringContainsString('#FFFFFF', $svg);
        $this->assertStringContainsString('#5FD3AA', $svg);
        // Le bleu nuit disparait : il serait invisible sur un fond sombre.
        $this->assertStringNotContainsString('#1D3A5C', $svg);
    }

    public function test_le_monogramme_seul_n_embarque_pas_le_mot_symbole(): void
    {
        $this->assertStringNotContainsString('WORKFLOW', $this->logo());
    }

    public function test_le_logo_complet_embarque_le_mot_symbole(): void
    {
        $svg = $this->logo(['lockup' => true]);

        $this->assertStringContainsString('WORKFLOW', $svg);
        $this->assertStringContainsString('Ɛ', $svg);
    }

    public function test_le_logo_porte_un_intitule_accessible(): void
    {
        $svg = $this->logo();

        $this->assertStringContainsString('role="img"', $svg);
        $this->assertStringContainsString('aria-labelledby', $svg);
        $this->assertStringContainsString(config('keneya.name'), $svg);
    }

    // ------------------------------------------------------ Les placements

    public function test_la_connexion_affiche_le_logo_complet_sans_texte_double(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('WORKFLOW', escape: false)
            ->assertSee('#1D3A5C', escape: false);

        // Le nom du produit ne doit plus apparaitre en texte a cote du logo :
        // seul le <title> de l'onglet le reprend.
        $contenu = $response->getContent();
        $corps = substr($contenu, strpos($contenu, '<body'));

        $this->assertStringNotContainsString('login__product', $contenu);
        $this->assertStringNotContainsString('<p class="login__product">', $corps);
    }

    public function test_la_barre_utilise_la_variante_claire(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get('/admin');

        $response->assertOk()
            ->assertSee('app-header__logo', escape: false)
            ->assertSee('#5FD3AA', escape: false)
            ->assertSee(hospital_name());

        // Le nom du produit n'est plus repris a cote du logo.
        $this->assertStringNotContainsString('app-header__product', $response->getContent());
    }

    public function test_l_ecran_de_salle_d_attente_utilise_la_variante_claire(): void
    {
        $response = $this->get('/board');

        $response->assertOk()
            ->assertSee('board__logo', escape: false)
            ->assertSee('WORKFLOW', escape: false)
            ->assertSee('#5FD3AA', escape: false);
    }

    // ---------------------------------------------------------- Le favicon

    public function test_les_trois_interfaces_declarent_le_favicon(): void
    {
        $pages = [
            '/connexion' => null,
            '/board' => null,
            '/admin' => $this->makeAdmin(),
            '/reception' => $this->makeReceptionist(),
            '/service' => $this->makeDoctor(Service::factory()->create())->user,
        ];

        foreach ($pages as $url => $user) {
            $response = $user ? $this->actingAs($user)->get($url) : $this->get($url);

            $response->assertOk()
                ->assertSee('favicon.svg', escape: false)
                ->assertSee('favicon.ico', escape: false)
                ->assertSee('apple-touch-icon.png', escape: false);
        }
    }

    public function test_les_fichiers_du_favicon_existent_et_ne_sont_pas_vides(): void
    {
        foreach (['favicon.ico', 'favicon.svg', 'apple-touch-icon.png'] as $fichier) {
            $chemin = public_path($fichier);

            $this->assertFileExists($chemin);
            $this->assertGreaterThan(0, filesize($chemin), "{$fichier} est vide.");
        }
    }

    /**
     * Le .ico doit contenir plusieurs tailles : un onglet, une barre de favoris
     * et un raccourci de bureau ne demandent pas la meme.
     */
    public function test_le_favicon_ico_contient_plusieurs_tailles(): void
    {
        $donnees = file_get_contents(public_path('favicon.ico'));

        $entete = unpack('vreserve/vtype/vcount', $donnees);

        $this->assertSame(0, $entete['reserve']);
        $this->assertSame(1, $entete['type']); // 1 = icone
        $this->assertGreaterThanOrEqual(3, $entete['count']);

        $tailles = [];
        for ($i = 0; $i < $entete['count']; $i++) {
            $tailles[] = ord($donnees[6 + 16 * $i]) ?: 256;
        }

        $this->assertSame([16, 32, 48], $tailles);
    }
}
