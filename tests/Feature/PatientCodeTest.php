<?php

namespace Tests\Feature;

use App\Actions\RegisterPatient;
use App\Models\Patient;
use App\Models\Service;
use App\Models\Visitor;
use App\Services\PatientCodeGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Le patient_code est attribue par l'Observer : quel que soit le point
 * d'entree, un patient ne peut pas exister sans identifiant unique. Il est
 * genere une seule fois, a la premiere venue, et ne change plus jamais.
 */
class PatientCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_le_code_est_genere_a_la_creation_directe_du_modele(): void
    {
        $this->assertSame('HFD-00001', Patient::factory()->create()->patient_code);
    }

    public function test_les_codes_se_suivent_et_restent_uniques(): void
    {
        $codes = collect(range(1, 5))->map(fn () => Patient::factory()->create()->patient_code);

        $this->assertSame(
            ['HFD-00001', 'HFD-00002', 'HFD-00003', 'HFD-00004', 'HFD-00005'],
            $codes->all(),
        );
        $this->assertCount(5, $codes->unique());
    }

    public function test_le_code_est_aussi_genere_via_l_action_d_enregistrement(): void
    {
        $service = Service::factory()->create();

        $visit = app(RegisterPatient::class)->execute([
            'name' => 'Fatoumata Coulibaly',
            'age' => 34,
            'gender' => 'Femme',
            'mobile' => '76112233',
            'crno' => 'CR-8891',
            'service_id' => $service->getKey(),
        ]);

        $this->assertSame('HFD-00001', $visit->patient->patient_code);
        $this->assertSame(1, $visit->token);
        $this->assertSame('CR-8891', $visit->patient->crno);

        // Le dossier papier est distinct de l'identifiant genere.
        $this->assertNotSame($visit->patient->crno, $visit->patient->patient_code);
    }

    public function test_un_code_fourni_explicitement_est_conserve(): void
    {
        $this->assertSame('HFD-09999', Patient::factory()->create(['patient_code' => 'HFD-09999'])->patient_code);
    }

    public function test_le_prefixe_de_l_etablissement_est_configurable(): void
    {
        config(['keneya.code_prefix' => 'CSREF']);

        $this->assertSame('CSREF-00001', Patient::factory()->create()->patient_code);
    }

    public function test_les_visiteurs_recoivent_leur_propre_serie(): void
    {
        Patient::factory()->create();

        $this->assertSame('HFD-V-00001', Visitor::factory()->create()->visitor_code);
    }

    /**
     * Le coeur de l'addendum v3 : un patient qui revient garde son identite.
     */
    public function test_un_second_passage_ne_cree_ni_patient_ni_code_supplementaire(): void
    {
        $service = Service::factory()->create();
        $patient = Patient::factory()->create();

        $this->makeVisit($service, [], $patient);
        $this->makeVisit($service, [], $patient);

        $this->assertSame(1, Patient::count());
        $this->assertSame(2, $patient->visits()->count());
        $this->assertSame('HFD-00001', $patient->refresh()->patient_code);
    }

    // ------------------------------------------- Series a lettre (v3.2.6)

    public function test_la_serie_numerique_passe_a_la_serie_a_apres_99999(): void
    {
        Patient::factory()->create(['patient_code' => 'HFD-99999']);

        // 100 000 dossiers ne tiennent pas sur cinq chiffres : une lettre
        // prend le relais plutot que d'allonger le numero.
        $this->assertSame('HFD-A0001', app(PatientCodeGenerator::class)->forPatient());
    }

    public function test_une_serie_a_lettre_s_incremente_sur_quatre_chiffres(): void
    {
        Patient::factory()->create(['patient_code' => 'HFD-A0001']);

        $this->assertSame('HFD-A0002', app(PatientCodeGenerator::class)->forPatient());
    }

    public function test_la_fin_d_une_serie_a_lettre_ouvre_la_suivante(): void
    {
        Patient::factory()->create(['patient_code' => 'HFD-A9999']);

        $this->assertSame('HFD-B0001', app(PatientCodeGenerator::class)->forPatient());
    }

    public function test_l_ordre_suit_le_code_et_non_l_ordre_de_creation(): void
    {
        // Un dossier de la serie A cree avant une reprise de la serie
        // numerique : trier par identifiant redonnerait 00043 pour dernier et
        // fabriquerait un doublon.
        Patient::factory()->create(['patient_code' => 'HFD-A0007']);
        Patient::factory()->create(['patient_code' => 'HFD-00042']);

        $this->assertSame('HFD-A0008', app(PatientCodeGenerator::class)->forPatient());
    }

    public function test_la_derniere_serie_refuse_d_aller_plus_loin(): void
    {
        Patient::factory()->create(['patient_code' => 'HFD-Z9999']);

        // Fabriquer un numero au-dela de Z9999 reviendrait a risquer un
        // doublon sur un dossier qui vaut a vie.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Z9999');

        app(PatientCodeGenerator::class)->forPatient();
    }

    public function test_les_fiches_visiteur_suivent_les_memes_series(): void
    {
        Visitor::factory()->create(['visitor_code' => 'HFD-V-99999']);

        $this->assertSame('HFD-V-A0001', app(PatientCodeGenerator::class)->forVisitor());
    }
}
