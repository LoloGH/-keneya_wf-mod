<?php

namespace Database\Factories;

use App\Models\BillableItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BillableItem>
 */
class BillableItemFactory extends Factory
{
    protected $model = BillableItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->randomElement([
                'Echographie abdominale',
                'Analyse — glycemie',
                'Radiographie thoracique',
                'Appendicectomie',
            ]),
            'service_id' => null,
            'price' => $this->faker->numberBetween(500, 50000),
        ];
    }
}
