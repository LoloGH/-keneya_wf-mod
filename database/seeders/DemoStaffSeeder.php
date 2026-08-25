<?php

namespace Database\Seeders;

use App\Models\Cashier;
use App\Models\Doctor;
use App\Models\Receptionist;
use App\Models\Service;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;

/**
 * Comptes de demonstration : un administrateur, une receptionniste et un
 * praticien par service clinique ou plateau technique.
 *
 * Les mots de passe proviennent du .env afin qu'un deploiement reel ne parte
 * pas avec des identifiants publics.
 */
class DemoStaffSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('keneya.seed_password');

        $this->makeUser('Administrateur', 'admin@keneya.local', $password, Roles::ADMIN);

        $receptionist = $this->makeUser('Awa Traore', 'accueil@keneya.local', $password, Roles::RECEPTIONIST);
        Receptionist::firstOrCreate(['user_id' => $receptionist->getKey()]);

        // Le caissier, quatrieme role cloisonne : les medecins n'encaissent pas.
        $cashier = $this->makeUser('Salif Konate', 'caisse@keneya.local', $password, Roles::CASHIER);
        Cashier::firstOrCreate(['user_id' => $cashier->getKey()]);

        $doctors = [
            ['Dr Modibo Keita', 'medecine@keneya.local', 'Medecine Generale', '76000001'],
            ['Dr Fatoumata Sidibe', 'urgences@keneya.local', 'Urgences', '76000002'],
            ['Sage-femme Kadiatou Diallo', 'maternite@keneya.local', 'Maternite', '76000003'],
            ['Dr Amadou Cisse', 'echographie@keneya.local', 'Echographie', '76000004'],
            ['Technicien Ousmane Bah', 'laboratoire@keneya.local', 'Laboratoire', '76000005'],
        ];

        foreach ($doctors as [$name, $email, $serviceName, $phone]) {
            $service = Service::where('name', $serviceName)->first();

            if (! $service) {
                continue;
            }

            $user = $this->makeUser($name, $email, $password, Roles::DOCTOR);

            Doctor::firstOrCreate(
                ['user_id' => $user->getKey(), 'service_id' => $service->getKey()],
                ['phone' => $phone],
            );
        }
    }

    private function makeUser(string $name, string $email, string $password, string $role): User
    {
        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => $password],
        );

        $user->syncRoles([$role]);

        return $user;
    }
}
