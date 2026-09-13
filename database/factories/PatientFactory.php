<?php

namespace Database\Factories;

use App\Models\Patient;
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
            // Le nom complet, et non les deux champs : PatientObserver le
            // decoupe. Un test qui pose ['name' => 'Sekou Diarra'] obtient
            // ainsi le patient qu'il a demande, et non un prenom tire au sort
            // par la fabrique par-dessus.
            'name' => $this->faker->name(),
            'age' => $this->faker->numberBetween(1, 95),
            'gender' => $this->faker->randomElement(['Homme', 'Femme']),
            'mobile' => '76'.$this->faker->numerify('######'),
            'crno' => null,
        ];
    }
}
