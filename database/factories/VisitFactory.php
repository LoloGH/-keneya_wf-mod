<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Service;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'service_id' => Service::factory(),
            'token' => $this->faker->numberBetween(1, 50),
            'status' => Visit::STATUS_WAITING,
            'opened_at' => now(),
        ];
    }

    public function called(): static
    {
        return $this->state(fn () => ['status' => Visit::STATUS_CALLED]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => Visit::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }
}
