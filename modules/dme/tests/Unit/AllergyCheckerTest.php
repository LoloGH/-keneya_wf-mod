<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Services\Prescriptions\AllergyChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Tests\TestCase;

/**
 * Contrôle d'allergie à la prescription (§22).
 *
 * Le comportement attendu est double : détecter les correspondances
 * pertinentes, et ne jamais produire de faux positif qui banaliserait
 * l'alerte auprès des prescripteurs.
 */
class AllergyCheckerTest extends TestCase
{
    use RefreshDatabase;

    private function patientAllergiqueA(string $allergen, string $severity = 'severe'): Patient
    {
        $patient = Patient::factory()->create();

        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => $allergen,
            'severity' => $severity,
            'status' => 'active',
        ]);

        return $patient->fresh();
    }

    public function test_une_correspondance_directe_est_detectee(): void
    {
        $patient = $this->patientAllergiqueA('Ibuprofène');

        $warnings = app(AllergyChecker::class)->match($patient, ['Ibuprofène 400 mg']);

        $this->assertCount(1, $warnings);
        $this->assertSame('Ibuprofène', $warnings[0]['allergen']);
        $this->assertSame('severe', $warnings[0]['severity']);
    }

    public function test_une_molecule_de_la_meme_famille_est_detectee(): void
    {
        $patient = $this->patientAllergiqueA('Pénicilline');

        $warnings = app(AllergyChecker::class)->match($patient, ['Amoxicilline 500 mg']);

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('famille', $warnings[0]['reason']);
    }

    public function test_la_detection_ignore_la_casse_et_les_accents(): void
    {
        $patient = $this->patientAllergiqueA('Pénicilline');

        $this->assertCount(1, app(AllergyChecker::class)->match($patient, ['PENICILLINE G']));
    }

    public function test_un_medicament_sans_rapport_ne_declenche_pas_d_alerte(): void
    {
        $patient = $this->patientAllergiqueA('Pénicilline');

        $this->assertSame([], app(AllergyChecker::class)->match($patient, ['Paracétamol 1 g']));
    }

    public function test_une_allergie_resolue_ne_declenche_pas_d_alerte(): void
    {
        $patient = Patient::factory()->create();

        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Pénicilline',
            'severity' => 'severe',
            'status' => 'resolved',
        ]);

        $this->assertSame([], app(AllergyChecker::class)->match($patient->fresh(), ['Amoxicilline']));
    }
}
