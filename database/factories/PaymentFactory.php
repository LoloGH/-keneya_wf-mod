<?php

namespace Database\Factories;

use App\Models\Patient;
use App\Models\Payment;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $patient = Patient::factory();

        return [
            'patient_id' => $patient,
            'visit_id' => Visit::factory(),
            'type' => Payment::TYPE_SERVICE,
            'service_id' => null,
            'billable_item_id' => null,
            'amount' => $this->faker->numberBetween(500, 20000),
            'catalog_price' => null,
            'status' => Payment::STATUS_PAID,
            'recorded_by_user_id' => User::factory(),
        ];
    }
}
