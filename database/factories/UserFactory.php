<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password = null;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= bcrypt('motdepasse'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Attribue l'un des trois roles cloisonnes, en creant le role au besoin
     * (utile dans les tests qui ne passent pas par RoleSeeder).
     */
    public function withRole(string $role): static
    {
        return $this->afterCreating(function (User $user) use ($role): void {
            Role::findOrCreate($role, 'web');
            $user->syncRoles([$role]);
        });
    }
}
