<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\LabOrder;
use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\User;

/**
 * @extends Factory<LabOrder>
 */
class LabOrderFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<LabOrder>
     */
    protected $model = LabOrder::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'requested_at' => now(),
            'priority' => 'routine',
            'status' => 'requested',
        ];
    }
}
