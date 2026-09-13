<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\User;
use Keneya\Dme\Standalone\StandaloneMode;
use RuntimeException;

/**
 * Praticien de développement du mode autonome.
 *
 * Le module n'a plus vocation à gérer ses propres comptes : c'est
 * l'application hôte qui authentifie. Ce seeder ne crée donc qu'un seul
 * compte, destiné au développement et aux tests, pour pouvoir ouvrir le
 * DME tant qu'aucun hôte ne le porte.
 *
 * Il refuse de s'exécuter si le mode autonome n'est pas actif : c'est ce
 * qui garantit qu'aucun compte ne peut apparaître dans une installation
 * réelle par simple exécution de `db:seed`.
 *
 * Le mot de passe provient de DME_STANDALONE_USER_PASSWORD. À défaut, un
 * mot de passe aléatoire est généré et affiché une seule fois : aucun mot
 * de passe par défaut n'est écrit dans le dépôt.
 */
class StandaloneDevSeeder extends Seeder
{
    public function run(): void
    {
        $standalone = app(StandaloneMode::class);

        if (! $standalone->enabled()) {
            throw new RuntimeException(
                'Le praticien de développement ne peut être créé que sous DME_STANDALONE_DEV=true, '
                .'hors production. En fonctionnement normal, les utilisateurs viennent de '
                .'l\'application hôte.'
            );
        }

        $definition = $standalone->devUser();

        $password = env('DME_STANDALONE_USER_PASSWORD');
        $generated = false;

        if (blank($password)) {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::query()->updateOrCreate(
            ['email' => $definition['email']],
            [
                'matricule' => 'DEV-001',
                'name' => $definition['name'],
                'first_name' => Str::before($definition['name'], ' '),
                'last_name' => Str::after($definition['name'], ' '),
                'title' => 'Dr',
                'speciality' => 'Développement',
                'password' => Hash::make($password),
                'service_id' => Service::query()->value('id'),
                'is_active' => true,
            ],
        );

        $user->syncRoles([$definition['role']]);

        $this->command?->info("Praticien de développement : {$definition['email']}");

        if ($generated) {
            $this->command?->warn("Mot de passe généré (affiché une seule fois) : {$password}");
        }
    }
}
