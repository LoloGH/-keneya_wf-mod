<?php

namespace Database\Factories;

use App\Models\Receptionist;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Receptionist>
 */
class ReceptionistFactory extends Factory
{
    protected $model = Receptionist::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->withRole(Roles::RECEPTIONIST),
        ];
    }
}
