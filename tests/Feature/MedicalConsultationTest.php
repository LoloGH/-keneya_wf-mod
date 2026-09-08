<?php

namespace Tests\Feature;

use App\Livewire\Service\MedicalConsultation;
use App\Models\Patient;
use App\Models\PatientHistory;
use App\Models\Service;
use App\Models\StaffType;
use App\Models\User;
use App\Models\Visit;
use App\Support\Audit;
use App\Support\Roles;
use Database\Seeders\DmePermissionSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Consultation;
use Keneya\Dme\Models\Patient as DossierMedical;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Consultation médicale rédigée depuis /service (v3.3.1).
 *
 * Ce que ces tests tiennent, et c'est tout le sens du chantier : le formulaire
 * est celui de WorkFlow, la donnée est celle du DME. Rien de clinique ne doit
 * atterrir dans le dossier de passage.
 */
class MedicalConsultationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRoles();
        $this->seed(DmePermissionSeeder::class);
    }

    /**
     * Un médecin de garde, un patient appelé dans sa file.
     *
     * @return array{0: User, 1: Visit, 2: Service}
     */
    private function medecinEtPatientAppele(bool $avecCapacite = true): array
    {
        $service = Service::factory()->create(['name' => 'Medecine Generale']);
        $doctor = $this->makeDoctor($service);

        $this->staffTypeFor(Roles::DOCTOR)->update([
            'capabilities' => $avecCapacite ? [StaffType::CAP_RECORD_CONSULTATION] : [],
        ]);

        $patient = Patient::factory()->create([
            'name' => 'Aminata Traore',
            'gender' => 'Femme',
            'age' => 34,
            'mobile' => '76000000',
        ]);

        $visit = $this->makeVisit($service, ['status' => Visit::STATUS_CALLED], $patient);

        return [$doctor->user, $visit, $service];
    }

    /** Le formulaire rempli, tel que le médecin le soumettrait. */
    private function remplir(Testable $ecran, int $visitId): Testable
    {
        return $ecran
            ->set('visitId', $visitId)
            ->set('reason', 'Fievre depuis trois jours.')
            ->set('historyOfIllness', 'Debut brutal, frissons, cephalees.')
            ->set('vitals.temperature', '39.2')
            ->set('vitals.heart_rate', '104')
            ->set('exam.general', 'Patiente asthenique, consciente.')
            ->set('exam.cardiovascular', 'Bruits du coeur reguliers.')
            ->set('diagnoses.0.label', 'Paludisme simple')
            ->set('diagnoses.0.code', 'B54')
            ->set('diagnoses.0.status', 'suspected')
            ->set('treatmentPlan', 'Artemether-lumefantrine, goutte epaisse.')
            ->set('recommendations', 'Boire abondamment, revenir si aggravation.');
    }

    // ------------------------------------------------- L'onglet et son accès

    public function test_l_onglet_consultation_n_apparait_qu_avec_la_capacite(): void
    {
        [$medecin, , $service] = $this->medecinEtPatientAppele(avecCapacite: true);

        $this->actingAs($medecin)->get(route('service.home'))
            ->assertOk()
            ->assertSee('Consultation');

        $this->staffTypeFor(Roles::DOCTOR)->update(['capabilities' => []]);

        // Sans la capacite, le formulaire lui-meme refuse, et pas seulement le menu.
        Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', Visit::first()->getKey())
            ->set('reason', 'Fievre.')
            ->call('save')
            ->assertForbidden();

        $this->assertDatabaseCount('dme_consultations', 0);
    }

    public function test_un_patient_d_un_autre_service_reste_hors_de_portee(): void
    {
        [$medecin, , $service] = $this->medecinEtPatientAppele();

        $autre = Service::factory()->create(['name' => 'Urgences']);
        $visiteEtrangere = $this->makeVisit($autre, ['status' => Visit::STATUS_CALLED]);

        // Meme refus que « Fin de consultation » : la visite est introuvable
        // hors du service du medecin, ce qui donne un 404 en HTTP — on ne
        // laisse pas deviner qu'elle existe ailleurs.
        $this->expectException(ModelNotFoundException::class);

        Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visiteEtrangere->getKey())
            ->set('reason', 'Fievre depuis trois jours.')
            ->call('save');
    }

    // --------------------------------------------------- Ce qui est enregistré

    public function test_la_consultation_atterrit_dans_le_dossier_medical(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        $this->remplir(
            Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
            $visit->getKey(),
        )->call('save')->assertHasNoErrors();

        $consultation = Consultation::firstOrFail();

        $this->assertSame('Fievre depuis trois jours.', $consultation->reason);
        $this->assertSame('completed', $consultation->status);
        // Le compte WorkFlow signe l'acte : c'est la table `users` partagee.
        $this->assertSame($medecin->getKey(), $consultation->doctor_id);

        // Constantes, examen par appareil et diagnostic, chacun dans sa table.
        $this->assertSame('39.2', (string) $consultation->vitalSigns()->value('temperature'));
        $this->assertSame(2, $consultation->clinicalNotes()->count());
        $this->assertDatabaseHas('dme_diagnoses', ['label' => 'Paludisme simple', 'code' => 'B54']);
    }

    public function test_le_dossier_medical_est_cree_au_premier_acte(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        $this->assertDatabaseCount('dme_patients', 0);

        $this->remplir(
            Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
            $visit->getKey(),
        )->call('save');

        // La liaison passe par la table d'identifiants du module, jamais par
        // un rapprochement sur le nom.
        $this->assertDatabaseCount('dme_patients', 1);
        $this->assertDatabaseHas('dme_patient_identifiers', [
            'system' => 'keneya_workflow',
            'value' => $visit->patient->patient_code,
        ]);
    }

    public function test_deux_consultations_ne_creent_qu_un_dossier(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        foreach (['Fievre depuis trois jours.', 'Controle a 48 heures.'] as $motif) {
            $this->remplir(
                Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
                $visit->getKey(),
            )->set('reason', $motif)->call('save');
        }

        $this->assertDatabaseCount('dme_patients', 1);
        $this->assertDatabaseCount('dme_consultations', 2);
    }

    // ------------------------------------- Rien de clinique ne reste dans WorkFlow

    public function test_aucun_contenu_clinique_ne_reste_dans_le_dossier_workflow(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        $this->remplir(
            Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
            $visit->getKey(),
        )->call('save');

        $consultation = Consultation::firstOrFail();
        $entree = PatientHistory::where('type', PatientHistory::TYPE_CONSULTATION)->latest('id')->firstOrFail();

        // WorkFlow ne garde qu'un renvoi vers l'acte : son numero, et rien
        // d'autre. C'est le cloisonnement du v3.3.1, tenu ici par un test
        // plutot que par une intention.
        $this->assertStringContainsString($consultation->consultation_number, $entree->description);

        foreach ([
            'Fievre depuis trois jours.',
            'Debut brutal, frissons, cephalees.',
            'Paludisme simple',
            'Artemether-lumefantrine, goutte epaisse.',
        ] as $clinique) {
            $this->assertStringNotContainsString($clinique, $entree->description);
        }
    }

    public function test_l_acte_est_journalise_sans_son_contenu(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        $this->remplir(
            Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
            $visit->getKey(),
        )->call('save');

        $trace = Activity::where('event', Audit::EVENT_MEDICAL_CONSULTATION)->latest('id')->firstOrFail();

        $this->assertStringContainsString(Consultation::firstOrFail()->consultation_number, $trace->description);
        $this->assertStringNotContainsString('Paludisme', $trace->description);
    }

    // -------------------------------------------------------- Garde-fous

    public function test_le_motif_est_exige(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('reason', '')
            ->call('save')
            ->assertHasErrors(['reason' => 'required']);

        $this->assertDatabaseCount('dme_consultations', 0);
    }

    public function test_une_constante_hors_bornes_est_refusee(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('reason', 'Fievre depuis trois jours.')
            ->set('vitals.temperature', '54')
            ->call('save')
            ->assertHasErrors('vitals.temperature');

        $this->assertDatabaseCount('dme_consultations', 0);
    }

    public function test_les_lignes_de_diagnostic_vides_sont_ecartees(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('visitId', $visit->getKey())
            ->set('reason', 'Fievre depuis trois jours.')
            ->call('addDiagnosis')
            ->call('addDiagnosis')
            ->set('diagnoses.1.label', 'Anemie')
            ->call('save')
            ->assertHasNoErrors();

        // Trois lignes ouvertes, une seule remplie : un dossier ne se remplit
        // pas de diagnostics vides.
        $this->assertDatabaseCount('dme_diagnoses', 1);
        $this->assertDatabaseHas('dme_diagnoses', ['label' => 'Anemie']);
    }

    public function test_la_derniere_ligne_de_diagnostic_se_vide_au_lieu_de_disparaitre(): void
    {
        [$medecin, , $service] = $this->medecinEtPatientAppele();

        $ecran = Livewire::actingAs($medecin)
            ->test(MedicalConsultation::class, ['serviceId' => $service->getKey()])
            ->set('diagnoses.0.label', 'Paludisme')
            ->call('removeDiagnosis', 0);

        // Un formulaire sans aucune ligne obligerait a cliquer pour
        // recommencer a ecrire.
        $this->assertCount(1, $ecran->get('diagnoses'));
        $this->assertSame('', $ecran->get('diagnoses')[0]['label']);
    }

    public function test_le_dossier_medical_reprend_l_identite_du_patient_workflow(): void
    {
        [$medecin, $visit, $service] = $this->medecinEtPatientAppele();

        $this->remplir(
            Livewire::actingAs($medecin)->test(MedicalConsultation::class, ['serviceId' => $service->getKey()]),
            $visit->getKey(),
        )->call('save');

        $dossier = DossierMedical::firstOrFail();

        // Le nom de WorkFlow part entier (v3.3.1) : le laisser couper au
        // premier espace reordonnerait le patient en « Traore Aminata » sur
        // l'ordonnance imprimee et sur chaque document du dossier.
        $this->assertSame('Aminata Traore', $dossier->last_name);
        $this->assertSame('Aminata Traore', $dossier->fullName());
        $this->assertSame('female', $dossier->sex);
        $this->assertSame('76000000', $dossier->phone);
    }
}
