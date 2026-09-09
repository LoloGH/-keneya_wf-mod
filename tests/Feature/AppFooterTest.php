<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pied de page de l'editeur, commun a toutes les interfaces.
 *
 * Il est pose dans les gabarits et non dans les pages : ces tests verifient
 * qu'il apparait sur chacune des interfaces existantes, et c'est aussi ce qui
 * garantit qu'une page ajoutee plus tard l'aura, puisqu'elle passera par l'un
 * de ces memes gabarits.
 */
class AppFooterTest extends TestCase
{
    use RefreshDatabase;

    private function assertPiedDePage(string $contenu): void
    {
        $this->assertStringContainsString(config('keneya.name'), $contenu);
        $this->assertStringContainsString("un produit d'", $contenu);
        $this->assertStringContainsString('href="https://sukaxess.com"', $contenu);
        $this->assertStringContainsString('Tous droits reserves', $contenu);
        $this->assertStringContainsString((string) date('Y'), $contenu);

        // L'etiquette de version (v3.2.8, point 5). Elle est verifiee dans le
        // pied de page commun, donc sur chacune des interfaces : c'est ce qui
        // garantit qu'une page ajoutee plus tard la portera aussi.
        $this->assertStringContainsString('v'.config('keneya.version'), $contenu);
    }

    /**
     * La version affichee doit venir du fichier `VERSION`, et de nulle part
     * ailleurs : une version affichee qui contredirait le depot serait pire que
     * pas de version du tout, et c'est ce meme fichier que le tag Git suit.
     */
    public function test_la_version_affichee_est_celle_du_fichier_version(): void
    {
        $fichier = trim((string) file_get_contents(base_path('VERSION')));

        $this->assertSame($fichier, config('keneya.version'));
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $fichier);
    }

    public function test_l_interface_d_administration_porte_le_pied_de_page(): void
    {
        $response = $this->actingAs($this->makeAdmin())->get('/admin');

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    public function test_l_accueil_porte_le_pied_de_page(): void
    {
        $response = $this->actingAs($this->makeReceptionist())->get('/reception');

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    public function test_la_caisse_porte_le_pied_de_page(): void
    {
        $response = $this->actingAs($this->makeCashier())->get('/caisse');

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    public function test_le_service_clinique_porte_le_pied_de_page(): void
    {
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);

        $response = $this->actingAs($doctor->user)->get('/service');

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    public function test_la_salle_d_attente_porte_le_pied_de_page(): void
    {
        $response = $this->get('/board');

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    public function test_le_portail_patient_porte_le_pied_de_page(): void
    {
        $patient = Patient::factory()->create();

        $response = $this->get(route('portal.show', $patient->portal_token));

        $response->assertOk();
        $this->assertPiedDePage($response->getContent());
    }

    /**
     * Les documents imprimes en sont volontairement exclus : un ticket ou une
     * ordonnance porte l'en-tete de l'etablissement, pas la signature de
     * l'editeur.
     */
    public function test_les_documents_imprimes_ne_portent_pas_le_pied_de_page(): void
    {
        $service = Service::factory()->create();
        $visit = $this->makeVisit($service);

        $response = $this->actingAs($this->makeReceptionist())
            ->get(route('reception.ticket.patient', $visit));

        $response->assertOk();
        $this->assertStringNotContainsString("un produit d'", $response->getContent());
    }
}
