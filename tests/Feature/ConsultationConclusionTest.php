<?php

namespace Tests\Feature;

use App\Actions\RecordConsultationConclusion;
use App\Livewire\Service\ConsultationActions;
use App\Models\Pathology;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ecran « Fin de consultation ».
 *
 * La conclusion s'y ecrivait depuis la v3.2 point 5. Elle n'y est plus
 * (v3.3.1) : elle est passee au dossier medical, sous « Dossier medical >
 * Consultation », avec le motif, les constantes, l'examen et les diagnostics.
 *
 * La raison tient en une phrase : ce qui s'ecrivait ici n'atteignait jamais le
 * dossier du patient. Deux champs pour un meme geste, sur deux ecrans voisins,
 * etaient surtout une occasion de se tromper de place, et le medecin qui se
 * trompait ne le voyait pas.
 *
 * Reste sur cet ecran ce qui n'est pas une donnee de sante : l'ordonnance, le
 * prochain rendez-vous, et la pathologie du passage. Cette derniere ne sert pas
 * au soin mais au fonctionnement de l'etablissement, s'adresser plus tard a un
 * groupe de patients par SMS. Elle serait partie avec la conclusion si l'on n'y
 * avait pas pris garde, et la diffusion aurait perdu sa seule source sans que
 * rien ne le signale.
 */
class ConsultationConclusionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
    }

    // ------------------------------------- La conclusion a quitte WorkFlow

    /**
     * La methode a disparu, pas seulement son formulaire : un composant
     * Livewire s'appelle sans passer par l'ecran.
     */
    public function test_la_conclusion_ne_s_ecrit_plus_dans_workflow(): void
    {
        $this->assertFalse(
            method_exists(ConsultationActions::class, 'recordConclusion'),
            'La conclusion doit avoir quitte WorkFlow, methode comprise.',
        );

        $this->assertFalse(
            class_exists(RecordConsultationConclusion::class),
            'L\'action de conclusion n\'a plus de raison d\'exister.',
        );
    }

    /**
     * L'ecran dit ou la conclusion s'ecrit desormais. Sans cela, le medecin
     * chercherait un champ disparu sans savoir ou aller.
     */
    public function test_l_ecran_indique_ou_la_conclusion_s_ecrit(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        // L'ecran n'affiche ses onglets qu'une fois un patient appele : sans
        // lui, il invite a en appeler un et le panneau n'existe pas.
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->call('selectTab', 'conclusion')
            ->assertSee('Dossier medical')
            ->assertSee('Consultation')
            ->assertDontSee('Conclusion de la prise en charge')
            ->assertDontSee('Enregistrer la conclusion');
    }

    /**
     * L'ecran s'ouvre sur l'ordonnance : c'est desormais le premier geste de
     * fin de consultation qui s'y accomplit.
     */
    public function test_l_ecran_ouvre_sur_l_ordonnance(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->assertSet('tab', 'ordonnance');
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

    /**
     * Plus rien n'ecrit ce type d'entree. La constante subsiste pour relire
     * les lignes anterieures : un journal append-only ne se reecrit pas.
     */
    public function test_aucune_entree_de_conclusion_ne_s_ecrit_plus(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->call('recordPathology');

        $this->assertSame(
            0,
            PatientHistory::where('type', PatientHistory::TYPE_CONSULTATION_CONCLUSION)->count(),
        );
    }

    // ------------------------------------------- La pathologie, elle, reste

    public function test_la_pathologie_choisie_se_pose_sur_la_visite(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);
        $pathologie = Pathology::create(['name' => 'Hypertension arterielle']);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->call('selectTab', 'conclusion')
            ->set('pathologyId', $pathologie->getKey())
            ->call('recordPathology')
            ->assertHasNoErrors();

        $this->assertSame($pathologie->getKey(), $visit->fresh()->pathology_id);
    }

    /**
     * La verification qui compte : la pathologie ne conditionne rien. Un
     * passage sans pathologie notee est un passage ordinaire, pas un dossier
     * incomplet.
     */
    public function test_la_pathologie_reste_facultative(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->call('recordPathology')
            ->assertHasNoErrors();

        $this->assertNull($visit->fresh()->pathology_id);
    }

    /**
     * Elle se retire aussi : une pathologie notee par erreur ne doit pas
     * rester attachee au passage, et ce patient ne doit pas recevoir la
     * diffusion d'un groupe qui n'est pas le sien.
     */
    public function test_la_pathologie_se_retire(): void
    {
        $service = Service::factory()->create();
        $medecin = $this->makeDoctor($service);
        $pathologie = Pathology::create(['name' => 'Paludisme']);
        $visit = $this->makeVisit($service, [
            'status' => Visit::STATUS_CALLED,
            'pathology_id' => $pathologie->getKey(),
        ]);

        Livewire::actingAs($medecin->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('pathologyId', null)
            ->call('recordPathology')
            ->assertHasNoErrors();

        $this->assertNull($visit->fresh()->pathology_id);
    }
}
