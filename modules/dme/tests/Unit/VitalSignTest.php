<?php

declare(strict_types=1);

namespace Keneya\Dme\Tests\Unit;

use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\VitalSign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Keneya\Dme\Tests\TestCase;

/**
 * Constantes vitales (§20) : calcul de l'IMC et détection des valeurs
 * hors intervalle de référence.
 */
class VitalSignTest extends TestCase
{
    use RefreshDatabase;

    public function test_l_imc_est_calcule_automatiquement(): void
    {
        $patient = Patient::factory()->create();

        $vital = VitalSign::create([
            'patient_id' => $patient->id,
            'measured_at' => now(),
            'weight' => 72,
            'height' => 175,
        ]);

        // 72 / 1,75² = 23,51
        $this->assertSame(23.51, $vital->bmi);
    }

    public function test_l_imc_reste_nul_sans_taille(): void
    {
        $patient = Patient::factory()->create();

        $vital = VitalSign::create([
            'patient_id' => $patient->id,
            'measured_at' => now(),
            'weight' => 72,
        ]);

        $this->assertNull($vital->bmi);
    }

    public function test_une_constante_hors_norme_est_signalee(): void
    {
        $patient = Patient::factory()->create();

        $vital = VitalSign::create([
            'patient_id' => $patient->id,
            'measured_at' => now(),
            'temperature' => 39.2,
            'heart_rate' => 78,
        ]);

        $this->assertTrue($vital->isOutOfRange('temperature'));
        $this->assertFalse($vital->isOutOfRange('heart_rate'));
    }

    public function test_l_historique_des_constantes_n_est_jamais_ecrase(): void
    {
        $patient = Patient::factory()->create();

        foreach ([72.0, 74.0, 73.0] as $index => $weight) {
            VitalSign::create([
                'patient_id' => $patient->id,
                'measured_at' => now()->subMonths(3 - $index),
                'weight' => $weight,
            ]);
        }

        $this->assertSame(3, $patient->vitalSigns()->count());
        $this->assertEqualsCanonicalizing(
            [72.0, 74.0, 73.0],
            $patient->vitalSigns()->pluck('weight')->map(fn ($w) => (float) $w)->all(),
        );
    }
}
