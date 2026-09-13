<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\Patient;

/**
 * Patients fictifs pour les tests (§55 : aucune donnée réelle).
 *
 * @extends Factory<\Keneya\Dme\Models\Patient>
 */
class PatientFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<Patient>
     */
    protected $model = Patient::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sex = fake()->randomElement(['male', 'female']);

        return [
            'last_name' => fake()->lastName(),
            'first_name' => $sex === 'male' ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'sex' => $sex,
            'birth_date' => fake()->dateTimeBetween('-85 years', '-1 year')->format('Y-m-d'),
            'nationality' => 'Malienne',
            'phone' => '+2237'.fake()->numerify('0######'),
            'city' => 'Bamako',
            'country' => 'Mali',
            'blood_group' => fake()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'status' => 'active',
        ];
    }

    public function deceased(): static
    {
        return $this->state(fn () => [
            'status' => 'deceased',
            'deceased_at' => fake()->dateTimeBetween('-2 years')->format('Y-m-d'),
        ]);
    }
}
