<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Keneya\Dme\Models\Patient;
use Keneya\Dme\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\Appointment;

/**
 * @extends Factory<\Keneya\Dme\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<Appointment>
     */
    protected $model = Appointment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'doctor_id' => User::factory(),
            'scheduled_for' => now()->addDays(3)->setTime(9, 30),
            'duration_minutes' => 30,
            'reason' => 'Consultation de suivi',
            'status' => 'scheduled',
        ];
    }
}
