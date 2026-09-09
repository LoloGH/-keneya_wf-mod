<?php

namespace Tests\Feature;

use App\Livewire\Service\MyPatients;
use App\Livewire\Service\PatientRecordPanel;
use App\Livewire\Shared\VerticalTabNav;
use App\Models\PatientHistory;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * L'espace de travail et le theme (v3.3.1).
 *
 * Deux reglages d'ecran, verifies ici parce qu'ils se defont sans bruit : une
 * colonne qui se reserve pour rien retrecit chaque page, et un theme qui ne se
 * relit pas avant le rendu fait clignoter la page en blanc a chaque
 * navigation.
 */
class WorkspaceLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ------------------------------------------- Le dossier et sa colonne

    /**
     * Ferme, le panneau ne rend rien : c'est cet attribut absent qui laisse la
     * grille sur une seule colonne, et l'espace de travail sur toute la
     * largeur.
     */
    public function test_le_dossier_ferme_n_occupe_aucune_place(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        Livewire::actingAs($medecin->user)
            ->test(PatientRecordPanel::class)
            ->assertDontSee('data-ouvert', escape: false)
            ->assertDontSee('Selectionnez un patient');
    }

    public function test_le_dossier_ouvert_prend_sa_colonne(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        Livewire::actingAs($medecin->user)
            ->test(PatientRecordPanel::class)
            ->call('open', $visit->patient_id)
            ->assertSee('data-ouvert', escape: false)
            ->assertSee($visit->patient->patient_code);
    }

    /**
     * Changer de section rend l'ecran a son travail : un dossier consulte
     * depuis la file n'a aucune raison de retrecir l'ecran des antecedents.
     */
    public function test_changer_de_section_referme_le_dossier(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        $panneau = Livewire::actingAs($medecin->user)
            ->test(PatientRecordPanel::class)
            ->call('open', $visit->patient_id)
            ->assertSet('patientId', $visit->patient_id);

        // L'evenement qu'emet la navigation quand on change de section.
        $panneau->dispatch('section-changee')->assertSet('patientId', null);
    }

    public function test_la_navigation_annonce_le_changement_de_section(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        Livewire::actingAs($medecin->user)
            ->test(VerticalTabNav::class, [
                'sections' => [
                    ['key' => 'file', 'label' => 'File', 'view' => 'sections.service.queue'],
                    ['key' => 'planning', 'label' => 'Planning', 'view' => 'sections.service.schedule'],
                ],
                'context' => ['serviceId' => $service->getKey()],
            ])
            ->call('select', 'planning')
            ->assertDispatched('section-changee');
    }

    /**
     * Les actions de meme rang ont la meme largeur.
     *
     * Un bouton se dimensionne sur son texte : « Envoyer le lien de mes
     * documents » paraissait plus important que « Donner un rendez-vous » par
     * la seule longueur de son libelle. Elles ne le sont pas.
     */
    public function test_les_actions_de_la_carte_patient_ont_la_meme_largeur(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service);

        // « Mes patients » liste ceux dont ce medecin porte une trace de prise
        // en charge : sans elle, la liste serait vide.
        PatientHistory::create([
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->getKey(),
            'type' => PatientHistory::TYPE_CONSULTATION,
            'service_id' => $service->getKey(),
            'doctor_id' => $medecin->getKey(),
            'description' => 'Consultation initiale.',
        ]);

        Livewire::actingAs($medecin->user)
            ->test(MyPatients::class)
            ->assertSee('btn-row--egaux', escape: false);
    }

    // ------------------------------------------------------------- Le theme

    /**
     * Le theme est relu avant le premier rendu, et non apres : une page peinte
     * en clair puis basculee donne un eclair blanc a chaque navigation.
     */
    public function test_le_theme_est_relu_avant_le_premier_rendu(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());

        $reponse = $this->actingAs($medecin->user)->get('/service')->assertOk();
        $html = $reponse->getContent();

        $script = strpos($html, "localStorage.getItem('keneya.theme')");
        $feuille = strpos($html, 'css/app.css');

        $this->assertNotFalse($script, 'Le theme enregistre n\'est pas relu.');
        $this->assertLessThan($feuille, $script, 'Le theme est relu apres la feuille de style.');
    }

    public function test_la_bascule_se_trouve_dans_la_barre(): void
    {
        $medecin = $this->makeDoctor(Service::factory()->create());

        $this->actingAs($medecin->user)
            ->get('/service')
            ->assertOk()
            ->assertSee('data-testid="theme-toggle"', escape: false);
    }

    /**
     * L'ecran de connexion garde sa scene claire : sa carte est posee sur une
     * photographie, et aucun agent n'y est connecte pour regler quoi que ce
     * soit.
     */
    public function test_l_ecran_de_connexion_reste_clair(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="clair"', escape: false)
            ->assertDontSee('theme-toggle');
    }
}
