<?php

namespace Tests\Feature;

use App\Actions\CreatePrescription;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\View\ComponentAttributeBag;
use Tests\TestCase;

/**
 * Identite visuelle : le logo fourni par le porteur du projet, ses deux
 * declinaisons, le favicon, et sa presence sur les documents imprimes.
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
            'svg' => false,
            'attributes' => new ComponentAttributeBag([]),
        ], $props))->render();
    }

    // ------------------------------------------------------- Les variantes

    public function test_la_variante_couleur_sert_le_monogramme_d_origine(): void
    {
        $rendu = $this->logo();

        $this->assertStringContainsString('images/keneya-icone.png', $rendu);
        $this->assertStringNotContainsString('keneya-icone-claire', $rendu);
    }

    public function test_la_variante_claire_sert_la_declinaison_pour_fond_sombre(): void
    {
        $this->assertStringContainsString(
            'images/keneya-icone-claire.png',
            $this->logo(['variant' => 'light'])
        );
    }

    public function test_le_monogramme_seul_n_embarque_pas_le_mot_symbole(): void
    {
        $rendu = $this->logo();

        $this->assertStringContainsString('keneya-icone', $rendu);
        $this->assertStringNotContainsString('keneya-logo', $rendu);
    }

    public function test_le_logo_complet_est_un_autre_fichier(): void
    {
        $this->assertStringContainsString('images/keneya-logo.png', $this->logo(['lockup' => true]));
        $this->assertStringContainsString(
            'images/keneya-logo-clair.png',
            $this->logo(['lockup' => true, 'variant' => 'light'])
        );
    }

    public function test_le_logo_porte_un_intitule_accessible(): void
    {
        $this->assertStringContainsString('alt="'.config('keneya.name').'"', $this->logo());
    }

    /**
     * Les dimensions reelles sont annoncees au navigateur : sans elles, la barre
     * de navigation sursaute au moment ou l'image arrive.
     */
    public function test_le_logo_annonce_ses_dimensions(): void
    {
        $this->assertStringContainsString('width="512" height="405"', $this->logo());
        $this->assertStringContainsString('width="900" height="420"', $this->logo(['lockup' => true]));
    }

    /**
     * Dans le decor de la page de connexion, le logo est pose a l'interieur
     * d'une illustration SVG, ou une balise <img> n'a pas cours.
     */
    public function test_a_l_interieur_d_un_svg_le_logo_devient_une_balise_image(): void
    {
        $rendu = $this->logo(['svg' => true, 'variant' => 'light']);

        $this->assertStringContainsString('<image', $rendu);
        $this->assertStringContainsString('href=', $rendu);
        $this->assertStringNotContainsString('<img', $rendu);
    }

    // ------------------------------------------------------ Les placements

    public function test_la_connexion_affiche_le_logo_complet_sans_texte_double(): void
    {
        $response = $this->get('/connexion');

        $response->assertOk()
            ->assertSee('images/keneya-logo.png', escape: false);

        // Le nom du produit ne doit pas apparaitre en texte a cote du logo :
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
            ->assertSee('images/keneya-icone-claire.png', escape: false)
            ->assertSee(hospital_name());

        $this->assertStringNotContainsString('app-header__product', $response->getContent());
    }

    public function test_l_ecran_de_salle_d_attente_utilise_la_variante_claire(): void
    {
        $this->get('/board')
            ->assertOk()
            ->assertSee('board__logo', escape: false)
            ->assertSee('images/keneya-logo-clair.png', escape: false);
    }

    /**
     * Les quatre declinaisons sont servies depuis public/ : une seule manquante
     * laisserait un cadre vide dans la barre de navigation.
     */
    public function test_les_quatre_declinaisons_existent(): void
    {
        foreach ([
            'images/keneya-logo.png',
            'images/keneya-logo-clair.png',
            'images/keneya-icone.png',
            'images/keneya-icone-claire.png',
            'images/keneya-icone-impression.png',
        ] as $fichier) {
            $this->assertFileExists(public_path($fichier));
            $this->assertGreaterThan(0, filesize(public_path($fichier)), "{$fichier} est vide.");
        }
    }

    /**
     * Les fichiers d'origine restent dans le depot : toute nouvelle declinaison
     * doit en partir, pas d'une image deja reduite.
     */
    public function test_les_fichiers_d_origine_sont_conserves(): void
    {
        foreach (['images/keneya-logo-source.svg', 'images/keneya-icone-source.svg'] as $fichier) {
            $this->assertFileExists(public_path($fichier));
        }
    }

    // ------------------------------------------------- Documents imprimes

    public function test_l_ordonnance_imprimable_porte_le_logo(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        $prescription = app(CreatePrescription::class)->execute($visit, $doctor, [
            ['medicament' => 'Paracetamol 500 mg'],
        ]);

        $this->actingAs($doctor->user)
            ->get(route('service.prescription.print', $prescription))
            ->assertOk()
            ->assertSee('images/keneya-icone-impression.png', escape: false);
    }

    /**
     * Dans le PDF, dompdf lit l'image sur le disque : c'est un chemin de
     * fichier qui doit figurer dans le gabarit, pas une URL.
     */
    public function test_le_gabarit_pdf_pointe_vers_un_fichier_du_disque(): void
    {
        $gabarit = file_get_contents(resource_path('views/pdf/prescription.blade.php'));

        $this->assertStringContainsString("public_path('images/keneya-icone-impression.png')", $gabarit);
    }

    /**
     * Le monogramme des impressions est aplati sur du blanc, sans couche alpha.
     * dompdf range la transparence d'un PNG dans un masque separe qu'il ne
     * compresse pas : le fichier des ecrans, transparent et cinq fois plus
     * grand, ajoutait une centaine de kilo-octets a chaque ordonnance PDF. Le
     * papier etant blanc, la transparence n'y sert a rien.
     */
    public function test_le_monogramme_des_impressions_est_aplati_sur_du_blanc(): void
    {
        $chemin = public_path('images/keneya-icone-impression.png');

        // Dans l'entete IHDR d'un PNG, l'octet 25 porte le type de couleur :
        // 4 et 6 sont les deux types qui embarquent une couche alpha.
        $type = ord(file_get_contents($chemin, false, null, 25, 1));

        $this->assertNotContains($type, [4, 6], 'Le monogramme des impressions a une couche alpha.');
        $this->assertLessThan(40_000, filesize($chemin), 'Le monogramme des impressions a grossi.');
    }

    public function test_le_pdf_de_l_ordonnance_s_engendre(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        $prescription = app(CreatePrescription::class)->execute($visit, $doctor, [
            ['medicament' => 'Paracetamol 500 mg'],
        ]);

        $reponse = $this->actingAs($doctor->user)
            ->get(route('service.prescription.pdf', $prescription))
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }

    public function test_le_ticket_d_accueil_porte_le_logo(): void
    {
        $gabarit = file_get_contents(resource_path('views/reception/print-ticket.blade.php'));

        $this->assertStringContainsString("asset('images/keneya-icone-impression.png')", $gabarit);
        $this->assertStringContainsString('ticket__logo', $gabarit);
    }

    public function test_le_recu_de_caisse_porte_le_logo(): void
    {
        $gabarit = file_get_contents(resource_path('views/print/receipt.blade.php'));

        $this->assertStringContainsString("asset('images/keneya-icone-impression.png')", $gabarit);
        $this->assertStringContainsString('ticket__logo', $gabarit);
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
