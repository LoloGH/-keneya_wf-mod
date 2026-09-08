<?php

namespace Tests\Feature;

use App\Actions\Dme\CreateMedicalPrescription;
use App\Livewire\Portal\PatientPortal;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Models\Prescription;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Telechargement de l'ordonnance depuis le portail patient.
 *
 * Le patient dispose deja de ses pieces jointes ; son ordonnance doit l'etre
 * autant, et sous les memes conditions d'acces.
 *
 * Depuis la v3.3.1 l'ordonnance vit dans le dossier medical : c'est donc le
 * dossier du patient, et non plus sa fiche WorkFlow, qui decide de ce qu'il
 * a le droit de lire.
 */
class PortalPrescriptionPdfTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<int, array{medicament: string, posologie?: string, duree?: string}>  $lignes
     */
    private function ordonnance(Doctor $doctor, Visit $visit, array $lignes): Prescription
    {
        return app(CreateMedicalPrescription::class)->execute($visit, $doctor, $lignes);
    }

    /**
     * Le PDF d'une ordonnance est aussi sensible que la piece jointe qu'il
     * accompagne : connaitre son identifiant ne doit pas suffire a la lire.
     */
    public function test_le_pdf_de_l_ordonnance_exige_le_code_valide(): void
    {
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, [], $patient);

        $ordonnance = $this->ordonnance($doctor, $visit, [['medicament' => 'Paracetamol']]);

        $this->get(route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]))
            ->assertForbidden();
    }

    public function test_l_ordonnance_d_un_autre_patient_reste_introuvable(): void
    {
        $patient = Patient::factory()->create();
        $autre = Patient::factory()->create();
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, [], $autre);

        $ordonnance = $this->ordonnance($doctor, $visit, [['medicament' => 'Paracetamol']]);

        $this->withSession(['portal.'.$patient->getKey() => true])
            ->get(route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]))
            ->assertNotFound();
    }

    /**
     * Un patient dont aucun acte n'a encore ete pose n'a pas de dossier
     * medical : l'ordonnance d'autrui ne doit pas devenir lisible pour
     * autant.
     */
    public function test_un_patient_sans_dossier_medical_ne_lit_rien(): void
    {
        $patient = Patient::factory()->create();
        $autre = Patient::factory()->create();
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, [], $autre);

        $ordonnance = $this->ordonnance($doctor, $visit, [['medicament' => 'Paracetamol']]);

        $this->assertNull($patient->dossierMedical());

        $this->withSession(['portal.'.$patient->getKey() => true])
            ->get(route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]))
            ->assertNotFound();
    }

    /**
     * Chaque medicament apparait sur sa propre ligne (rendu `ordo-lu`), et le
     * lien de telechargement est propose au patient.
     */
    public function test_l_ordonnance_est_mise_en_page_et_telechargeable(): void
    {
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, [], $patient);

        $ordonnance = $this->ordonnance($doctor, $visit, [
            ['medicament' => 'Paracetamol', 'posologie' => '1/2', 'duree' => '5 jours'],
            ['medicament' => 'Aspirine', 'posologie' => '1/3', 'duree' => '4 jours'],
        ]);

        Livewire::test(PatientPortal::class, ['token' => $patient->portal_token])
            ->set('code', $patient->access_code)
            ->call('unlock')
            ->assertHasNoErrors()
            ->assertSee('Paracetamol')
            ->assertSee('Aspirine')
            ->assertSee('ordo-lu', escape: false)
            ->assertSee(route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]), escape: false);
    }

    /**
     * Le telechargement lui-meme, code valide : un vrai PDF sort.
     */
    public function test_le_pdf_se_telecharge_apres_le_code(): void
    {
        $patient = Patient::factory()->create();
        $service = Service::factory()->create();
        $doctor = $this->makeDoctor($service);
        $visit = $this->makeVisit($service, [], $patient);

        $ordonnance = $this->ordonnance($doctor, $visit, [['medicament' => 'Paracetamol']]);

        $reponse = $this->withSession(['portal.'.$patient->getKey() => true])
            ->get(route('portal.prescription.pdf', [$patient->portal_token, $ordonnance]))
            ->assertOk();

        $this->assertStringStartsWith('%PDF', $reponse->getContent());
    }
}
