<?php

namespace Tests\Feature;

use App\Livewire\Service\ConsultationActions;
use App\Models\Pathology;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ecran « Fin de consultation » (v3.2 point 5, remis en service en v3.2.9).
 *
 * `recordConclusion()` existait dans le composant, mais aucune vue ne
 * l'atteignait : l'onglet affiche appelait `selectTab('caisse')`, valeur que
 * le composant n'accepte pas et qui retombait sur `conclusion` — un onglet
 * sans panneau. La conclusion de consultation etait donc inaccessible, et
 * l'ecran s'ouvrait vide.
 *
 * Ces tests fixent le comportement pour que le panneau ne puisse plus
 * disparaitre sans qu'on le voie.
 */
class ConsultationConclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    public function test_l_ecran_ouvre_sur_le_panneau_de_conclusion(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->assertSet('tab', 'conclusion')
            ->assertSee('Conclusion de la prise en charge')
            ->assertSee('Enregistrer la conclusion');
    }

    /**
     * L'onglet retire : un encaissement se fait a la caisse depuis la v3.2
     * point 6. Le bouton pointait sur une methode absente du composant.
     */
    public function test_l_ecran_du_medecin_n_offre_plus_d_encaissement(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->assertDontSee('Caisse Services')
            ->assertDontSee('recordPayment');
    }

    public function test_une_conclusion_s_enregistre_dans_le_dossier(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('conclusion', 'Tension stabilisee, controle dans un mois.')
            ->call('recordConclusion')
            ->assertHasNoErrors();

        $ligne = PatientHistory::where('type', PatientHistory::TYPE_CONSULTATION_CONCLUSION)->sole();

        $this->assertStringContainsString('Tension stabilisee', $ligne->description);
    }

    /**
     * La verification qui compte pour le point 1 : la pathologie ne doit
     * jamais bloquer une cloture de consultation.
     */
    public function test_une_conclusion_s_enregistre_sans_pathologie(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('conclusion', 'Rien a signaler.')
            ->set('pathologyId', null)
            ->call('recordConclusion')
            ->assertHasNoErrors();

        $this->assertNull($visit->fresh()->pathology_id);
        $this->assertSame(1, PatientHistory::where('type', PatientHistory::TYPE_CONSULTATION_CONCLUSION)->count());
    }

    public function test_la_pathologie_choisie_se_pose_sur_la_visite(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $pathologie = Pathology::create(['name' => 'Hypertension arterielle']);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('conclusion', 'Traitement antihypertenseur instaure.')
            ->set('pathologyId', $pathologie->getKey())
            ->call('recordConclusion')
            ->assertHasNoErrors();

        $this->assertSame($pathologie->getKey(), $visit->fresh()->pathology_id);
    }
}
