<?php

namespace Database\Factories;

use App\Models\Service;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'mobile' => '76'.$this->faker->numerify('######'),
            'service_id' => Service::factory(),
            'reason' => 'Visite a un proche',
        ];
    }
}
