<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use Keneya\Dme\Models\Allergy;
use Keneya\Dme\Models\ChronicCondition;
use Keneya\Dme\Models\Patient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Tests\TestCase;

/**
 * Modèle Patient : identifiant, âge, recherche et alertes cliniques.
 */
class PatientTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_numero_de_dossier_est_attribue_a_la_creation(): void
    {
        $patient = Patient::factory()->create();

        $this->assertMatchesRegularExpression('/^PAT-\d{4}-\d{6}$/', $patient->patient_number);
    }

    public function test_le_numero_de_dossier_reste_stable_apres_modification(): void
    {
        $patient = Patient::factory()->create();
        $number = $patient->patient_number;

        $patient->update(['last_name' => 'Nouveau nom', 'city' => 'Ségou']);

        $this->assertSame($number, $patient->fresh()->patient_number);
    }

    public function test_l_age_est_calcule_a_partir_de_la_date_de_naissance(): void
    {
        $patient = Patient::factory()->create([
            'birth_date' => now()->subYears(42)->subMonths(3)->toDateString(),
        ]);

        $this->assertSame(42, $patient->age());
        $this->assertSame('42 ans', $patient->ageLabel());
    }

    public function test_l_age_est_inconnu_sans_date_de_naissance(): void
    {
        $patient = Patient::factory()->create(['birth_date' => null]);

        $this->assertNull($patient->age());
        $this->assertSame('Âge inconnu', $patient->ageLabel());
    }

    public function test_une_allergie_severe_alimente_les_alertes_du_dossier(): void
    {
        $patient = Patient::factory()->create();

        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Pénicilline',
            'severity' => 'severe',
            'status' => 'active',
        ]);

        Allergy::create([
            'patient_id' => $patient->id,
            'allergen' => 'Poussière',
            'severity' => 'mild',
            'status' => 'active',
        ]);

        $critical = $patient->fresh()->load('allergies')->criticalAllergies();

        $this->assertCount(1, $critical);
        $this->assertSame('Pénicilline', $critical->first()->allergen);
    }

    public function test_les_pathologies_chroniques_actives_sont_listees(): void
    {
        $patient = Patient::factory()->create();

        ChronicCondition::create(['patient_id' => $patient->id, 'label' => 'Diabète type 2', 'status' => 'active']);
        ChronicCondition::create(['patient_id' => $patient->id, 'label' => 'Asthme', 'status' => 'resolved']);

        $this->assertCount(1, $patient->fresh()->load('chronicConditions')->activeConditions());
    }

    public function test_la_recherche_couvre_nom_dossier_et_telephone(): void
    {
        $target = Patient::factory()->create([
            'last_name' => 'Traoré', 'first_name' => 'Mamadou', 'phone' => '+22370001001',
        ]);
        Patient::factory()->create(['last_name' => 'Sow', 'first_name' => 'Awa']);

        $this->assertSame($target->id, Patient::search('Traoré')->sole()->id);
        $this->assertSame($target->id, Patient::search('Mamadou')->sole()->id);
        $this->assertSame($target->id, Patient::search($target->patient_number)->sole()->id);
        $this->assertSame($target->id, Patient::search('70001001')->sole()->id);
    }

    public function test_la_recherche_accepte_une_date_de_naissance_au_format_francais(): void
    {
        $target = Patient::factory()->create(['birth_date' => '1984-03-12']);
        Patient::factory()->create(['birth_date' => '1990-01-01']);

        $this->assertSame($target->id, Patient::search('12/03/1984')->sole()->id);
    }
}
