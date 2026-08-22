<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Le patient_code est attribue par l'Observer : quel que soit le point
 * d'entree, un patient ne peut pas exister sans identifiant unique.
 */
class PatientCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_est_genere_a_la_creation_directe_du_modele(): void
    {
        $patient = Patient::factory()->create();

        $this->assertSame('HFD-00001', $patient->patient_code);
    }

    public function test_les_codes_se_suivent_et_restent_uniques(): void
    {
        $service = Service::factory()->create();

        $codes = collect(range(1, 5))
            ->map(fn () => Patient::factory()->for($service)->create()->patient_code);

        $this->assertSame(
            ['HFD-00001', 'HFD-00002', 'HFD-00003', 'HFD-00004', 'HFD-00005'],
            $codes->all(),
        );
        $this->assertCount(5, $codes->unique());
    }

    public function test_le_code_est_aussi_genere_via_l_action_d_enregistrement(): void
    {
        $service = Service::factory()->create();

        $patient = app(RegisterPatient::class)->execute([
            'name' => 'Fatoumata Coulibaly',
            'age' => 34,
            'gender' => 'Femme',
            'mobile' => '76112233',
            'crno' => 'CR-8891',
            'service_id' => $service->getKey(),
        ]);

        $this->assertSame('HFD-00001', $patient->patient_code);
        $this->assertSame(1, $patient->token);
        $this->assertSame(Patient::STATUS_WAITING, $patient->status);
        $this->assertSame('CR-8891', $patient->crno);

        // Le dossier papier est distinct de l'identifiant genere.
        $this->assertNotSame($patient->crno, $patient->patient_code);
    }

    public function test_un_code_fourni_explicitement_est_conserve(): void
    {
        $patient = Patient::factory()->create(['patient_code' => 'HFD-09999']);

        $this->assertSame('HFD-09999', $patient->patient_code);
    }

    public function test_le_prefixe_de_l_etablissement_est_configurable(): void
    {
        config(['keneya.code_prefix' => 'CSREF']);

        $this->assertSame('CSREF-00001', Patient::factory()->create()->patient_code);
    }

    public function test_les_visiteurs_recoivent_leur_propre_serie(): void
    {
        Patient::factory()->create();

        $visitor = Visitor::factory()->create();

        $this->assertSame('HFD-V-00001', $visitor->visitor_code);
    }
}
