<?php

namespace Tests\Feature;

use App\Livewire\Portal\PatientPortal;
use App\Models\Patient;
use App\Models\Prescription;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Telechargement de l'ordonnance depuis le portail patient.
 *
 * Le patient dispose deja de ses pieces jointes ; son ordonnance doit l'etre
 * autant, et sous les memes conditions d'acces.
 */
class PortalPrescriptionPdfTest extends TestCase
{
    use RefreshDatabase;

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

        $ordonnance = Prescription::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'content' => 'Paracetamol',
        ]);

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

        $ordonnance = Prescription::create([
            'patient_id' => $autre->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'content' => 'Paracetamol',
        ]);

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

        $ordonnance = Prescription::create([
            'patient_id' => $patient->getKey(),
            'visit_id' => $visit->getKey(),
            'doctor_id' => $doctor->getKey(),
            'content' => "1. Paracetamol — 1/2 — 5\n2. Aspirine — 1/3 — 4",
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
}
