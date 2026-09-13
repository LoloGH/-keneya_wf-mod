<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Factories;

use Keneya\Dme\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;
use Keneya\Dme\Models\User;
use Illuminate\Support\Str;

/**
 * @extends Factory<\Keneya\Dme\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * Le modèle ne se devine pas depuis un package : les fabriques
     * de Laravel supposent l'espace de noms App\Models.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $firstName = fake()->firstName();
        $lastName = fake()->lastName();

        return [
            'matricule' => strtoupper(Str::random(3)).'-'.fake()->unique()->numberBetween(100, 999),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'name' => $firstName.' '.$lastName,
            'title' => fake()->randomElement(['Dr', 'M.', 'Mme']),
            'speciality' => fake()->randomElement(['Médecine générale', 'Cardiologie', 'Soins généraux']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '+2237'.fake()->numerify('0######'),
            'password' => 'MotDePasseDeTest2026',
            'is_active' => true,
            'email_verified_at' => now(),
            'service_id' => null,
            'remember_token' => Str::random(10),
        ];
    }

    /** Compte désactivé : conserve son historique mais ne peut plus agir. */
    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }

    public function inService(Service $service): static
    {
        return $this->state(fn () => ['service_id' => $service->id]);
    }
}
