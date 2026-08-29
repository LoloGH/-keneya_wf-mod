<?php

namespace Tests\Feature;

use App\Actions\CreatePrescription;
use App\Livewire\Portal\PatientPortal;
use App\Livewire\Service\ConsultationActions;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use App\Models\Visit;
use App\Services\SmsGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ordonnance ligne a ligne (v3.2.6).
 *
 * L'ordonnance etait une zone de texte unique. Elle s'ecrit desormais ligne
 * par ligne, chacune avec son medicament, sa posologie et sa duree — et se
 * relit numerotee, a l'ecran comme a l'impression.
 */
class PrescriptionLinesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->mock(SmsGateway::class)->shouldReceive('send')->andReturnTrue();
    }

    /** @return array{0: Service, 1: Doctor, 2: Visit} */
    private function contexte(): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED]);

        return [$service, $doctor, $visit];
    }

    // ------------------------------------------------------ La saisie

    public function test_le_formulaire_ouvre_une_ligne_vide(): void
    {
        [$service, $doctor] = $this->contexte();

        // Le medecin n'a pas a cliquer pour commencer a ecrire.
        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->assertCount('prescriptionLines', 1);
    }

    public function test_le_bouton_ajoute_une_ligne_et_la_numerote(): void
    {
        [$service, $doctor] = $this->contexte();

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('tab', 'ordonnance')
            ->call('addPrescriptionLine')
            ->call('addPrescriptionLine')
            ->assertCount('prescriptionLines', 3)
            ->assertSeeInOrder(['1', '2', '3']);
    }

    public function test_le_nombre_de_lignes_est_borne(): void
    {
        [$service, $doctor] = $this->contexte();

        $composant = Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()]);

        // Une ordonnance n'a pas cinquante lignes : le garde-fou evite qu'un
        // clic reste appuye n'en cree mille.
        for ($i = 0; $i < ConsultationActions::MAX_LIGNES + 5; $i++) {
            $composant->call('addPrescriptionLine');
        }

        $composant->assertCount('prescriptionLines', ConsultationActions::MAX_LIGNES);
    }

    public function test_la_derniere_ligne_se_vide_au_lieu_de_disparaitre(): void
    {
        [$service, $doctor] = $this->contexte();

        // Sans champ, le formulaire n'aurait plus rien a remplir.
        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('prescriptionLines.0.medicament', 'Erreur de frappe')
            ->call('removePrescriptionLine', 0)
            ->assertCount('prescriptionLines', 1)
            ->assertSet('prescriptionLines.0.medicament', '');
    }

    public function test_une_ligne_du_milieu_se_retire_et_les_suivantes_remontent(): void
    {
        [$service, $doctor] = $this->contexte();

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('prescriptionLines.0.medicament', 'Premier')
            ->call('addPrescriptionLine')
            ->set('prescriptionLines.1.medicament', 'Deuxieme')
            ->call('addPrescriptionLine')
            ->set('prescriptionLines.2.medicament', 'Troisieme')
            ->call('removePrescriptionLine', 1)
            ->assertCount('prescriptionLines', 2)
            ->assertSet('prescriptionLines.0.medicament', 'Premier')
            ->assertSet('prescriptionLines.1.medicament', 'Troisieme');
    }

    // --------------------------------------------------- L'enregistrement

    public function test_l_ordonnance_conserve_ses_lignes_dans_l_ordre(): void
    {
        [$service, $doctor, $visit] = $this->contexte();

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'ordonnance')
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->set('prescriptionLines.0.posologie', '1 comprime matin et soir')
            ->set('prescriptionLines.0.duree', '5 jours')
            ->call('addPrescriptionLine')
            ->set('prescriptionLines.1.medicament', 'Amoxicilline 1 g')
            ->set('prescriptionLines.1.posologie', '2 fois par jour')
            ->call('savePrescription')
            ->assertHasNoErrors();

        $lignes = Prescription::firstOrFail()->lignes();

        $this->assertCount(2, $lignes);
        $this->assertSame('Paracetamol 500 mg', $lignes[0]['medicament']);
        $this->assertSame('5 jours', $lignes[0]['duree']);
        $this->assertSame('Amoxicilline 1 g', $lignes[1]['medicament']);
        // La duree n'a pas ete saisie : elle vaut « non precise », pas chaine
        // vide, pour que l'imprime laisse la case blanche.
        $this->assertNull($lignes[1]['duree']);
    }

    public function test_les_lignes_ouvertes_mais_non_remplies_sont_ecartees(): void
    {
        [$service, $doctor, $visit] = $this->contexte();

        // Le medecin a ouvert une ligne de plus sans la remplir : elle ne doit
        // pas se retrouver vide sur l'ordonnance imprimee.
        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'ordonnance')
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->call('addPrescriptionLine')
            ->call('addPrescriptionLine')
            ->call('savePrescription')
            ->assertHasNoErrors();

        $this->assertCount(1, Prescription::firstOrFail()->lignes());
    }

    public function test_une_posologie_sans_medicament_n_ordonne_rien(): void
    {
        [$service, $doctor, $visit] = $this->contexte();

        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'ordonnance')
            ->set('prescriptionLines.0.posologie', 'matin et soir')
            ->call('savePrescription')
            ->assertHasErrors('prescriptionLines');

        $this->assertSame(0, Prescription::count());
    }

    public function test_le_formulaire_repart_vide_apres_enregistrement(): void
    {
        [$service, $doctor, $visit] = $this->contexte();

        // Sinon l'ordonnance du patient precedent serait proposee au suivant.
        Livewire::actingAs($doctor->user)
            ->test(ConsultationActions::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('tab', 'ordonnance')
            ->set('prescriptionLines.0.medicament', 'Paracetamol 500 mg')
            ->call('savePrescription')
            ->assertCount('prescriptionLines', 1)
            ->assertSet('prescriptionLines.0.medicament', '');
    }

    public function test_l_action_refuse_une_ordonnance_sans_aucun_medicament(): void
    {
        [, $doctor, $visit] = $this->contexte();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('au moins un medicament');

        app(CreatePrescription::class)->execute($visit, $doctor, [['posologie' => 'matin']]);
    }

    // ------------------------------------------------------- La relecture

    public function test_une_ordonnance_ancienne_se_relit_ligne_par_ligne(): void
    {
        [, $doctor, $visit] = $this->contexte();

        // Ecrite avant la v3.2.6 : un seul bloc de texte, sans colonne.
        $ancienne = Prescription::create([
            'patient_id' => $visit->patient_id,
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'content' => "Paracetamol 500 mg\nAmoxicilline 1 g\n\n",
        ]);

        $lignes = $ancienne->lignes();

        $this->assertCount(2, $lignes);
        $this->assertSame('Paracetamol 500 mg', $lignes[0]['medicament']);
        $this->assertNull($lignes[0]['posologie']);
    }

    public function test_l_imprime_numerote_les_lignes(): void
    {
        [$service, $doctor] = $this->contexte();
        $patient = Patient::factory()->create(['name' => 'Moussa Keita']);
        $visit = $this->makeVisit($service, [], $patient);

        $prescription = app(CreatePrescription::class)->execute($visit, $doctor, [
            ['medicament' => 'Paracetamol 500 mg', 'posologie' => 'matin et soir', 'duree' => '5 jours'],
            ['medicament' => 'Amoxicilline 1 g'],
        ]);

        $this->actingAs($doctor->user)
            ->get(route('service.prescription.print', $prescription))
            ->assertOk()
            ->assertSeeInOrder(['Paracetamol 500 mg', 'matin et soir', '5 jours', 'Amoxicilline 1 g'])
            ->assertSee('Ordonnance medicale')
            ->assertSee('Cachet de l\'etablissement', escape: false);
    }

    public function test_le_portail_patient_affiche_les_lignes(): void
    {
        [$service, $doctor] = $this->contexte();
        $patient = Patient::factory()->create();
        $visit = $this->makeVisit($service, [], $patient);

        app(CreatePrescription::class)->execute($visit, $doctor, [
            ['medicament' => 'Paracetamol 500 mg', 'posologie' => 'matin et soir'],
        ]);

        // Le portail demande d'abord le code d'acces : c'est apres qu'il
        // montre les documents.
        Livewire::test(PatientPortal::class, ['token' => $patient->portal_token])
            ->set('code', $patient->access_code)
            ->call('unlock')
            ->assertSee('Paracetamol 500 mg')
            ->assertSee('matin et soir');
    }
}
