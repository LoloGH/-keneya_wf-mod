<?php

namespace Database\Factories;

use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    protected $model = Service::class;

    public function definition(): array
    {
        return [
            'name' => 'Service '.$this->faker->unique()->word(),
            'kind' => Service::KIND_CLINIQUE,
        ];
    }

    public function plateauTechnique(): static
    {
        return $this->state(fn () => ['kind' => Service::KIND_PLATEAU_TECHNIQUE]);
    }
}
