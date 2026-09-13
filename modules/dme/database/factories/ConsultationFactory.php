<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\Consultation;

/**
 * @extends Factory<\Keneya\Dme\Models\Consultation>
 */
class ConsultationFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<Consultation>
     */
    protected $model = Consultation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'started_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'type' => 'ambulatory',
            'status' => 'in_progress',
            'reason' => fake()->sentence(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['status' => 'completed', 'ended_at' => now()]);
    }
}
