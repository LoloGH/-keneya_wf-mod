<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\Prescription;

/**
 * @extends Factory<\Keneya\Dme\Models\Prescription>
 */
class PrescriptionFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<Prescription>
     */
    protected $model = Prescription::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'issued_on' => now()->toDateString(),
            'status' => 'draft',
        ];
    }

    public function validated(): static
    {
        return $this->state(fn () => ['status' => 'validated', 'validated_at' => now()]);
    }
}
