<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Service;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole(Roles::DOCTOR),
            'service_id' => Service::factory(),
            'phone' => '76'.$this->faker->numerify('######'),
        ];
    }
}
