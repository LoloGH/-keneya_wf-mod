<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Patient>
 */
class PatientFactory extends Factory
{
    protected $model = Patient::class;

    public function definition(): array
    {
        return [
            // patient_code volontairement absent : il est attribue par
            // PatientObserver, quel que soit le point d'entree.
            'name' => $this->faker->name(),
            'age' => $this->faker->numberBetween(1, 95),
            'gender' => $this->faker->randomElement(['Homme', 'Femme']),
            'mobile' => '76'.$this->faker->numerify('######'),
            'crno' => null,
            'service_id' => Service::factory(),
            'token' => $this->faker->numberBetween(1, 50),
            'status' => Patient::STATUS_WAITING,
        ];
    }
}
