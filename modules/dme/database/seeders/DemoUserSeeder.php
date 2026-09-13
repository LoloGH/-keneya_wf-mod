<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Keneya\Dme\Models\Service;
use Keneya\Dme\Models\User;
use Keneya\Dme\Support\Rbac;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Comptes professionnels de démonstration (§55).
 *
 * Deux garde-fous, volontairement stricts :
 *
 *  1. Le seeder refuse de s'exécuter hors des environnements local et
 *     testing : un déploiement de production ne peut pas créer de comptes
 *     de démonstration par inadvertance.
 *
 *  2. Aucun mot de passe n'est codé en dur. La valeur provient de
 *     DEMO_USER_PASSWORD ; sans elle, le seeder s'arrête avec un message
 *     explicite (§55, §66).
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('Comptes de démonstration ignorés : environnement non local.');

            return;
        }

        $password = config('dme.demo.password');

        if (blank($password)) {
            throw new RuntimeException(
                'DEMO_USER_PASSWORD n\'est pas défini. Renseignez cette variable dans votre .env '
                .'avant de créer les comptes de démonstration : aucun mot de passe par défaut '
                .'n\'est fourni par l\'application.'
            );
        }

        $services = Service::pluck('id', 'code');

        $users = [
            [
                'matricule' => 'ADM-001', 'title' => 'M.', 'first_name' => 'Ibrahim', 'last_name' => 'Coulibaly',
                'email' => 'admin@keneya.test', 'role' => Rbac::ROLE_ADMIN,
                'service' => 'ADM', 'speciality' => 'Administration système',
            ],
            [
                'matricule' => 'MED-001', 'title' => 'Dr', 'first_name' => 'Aïssatou', 'last_name' => 'Diallo',
                'email' => 'medecin@keneya.test', 'role' => Rbac::ROLE_DOCTOR,
                'service' => 'MI', 'speciality' => 'Médecine interne',
            ],
            [
                'matricule' => 'MED-002', 'title' => 'Dr', 'first_name' => 'Souleymane', 'last_name' => 'Keïta',
                'email' => 'cardiologue@keneya.test', 'role' => Rbac::ROLE_DOCTOR,
                'service' => 'CARD', 'speciality' => 'Cardiologie',
            ],
            [
                'matricule' => 'INF-001', 'title' => 'Mme', 'first_name' => 'Fatoumata', 'last_name' => 'Sidibé',
                'email' => 'infirmier@keneya.test', 'role' => Rbac::ROLE_NURSE,
                'service' => 'MI', 'speciality' => 'Soins généraux',
            ],
            [
                'matricule' => 'LAB-001', 'title' => 'M.', 'first_name' => 'Moussa', 'last_name' => 'Traoré',
                'email' => 'laboratoire@keneya.test', 'role' => Rbac::ROLE_LAB,
                'service' => 'LAB', 'speciality' => 'Biologie médicale',
            ],
            [
                'matricule' => 'RAD-001', 'title' => 'Dr', 'first_name' => 'Kadiatou', 'last_name' => 'Bah',
                'email' => 'radiologie@keneya.test', 'role' => Rbac::ROLE_RADIOLOGY,
                'service' => 'IMG', 'speciality' => 'Radiologie',
            ],
            [
                'matricule' => 'PHA-001', 'title' => 'M.', 'first_name' => 'Amadou', 'last_name' => 'Sow',
                'email' => 'pharmacien@keneya.test', 'role' => Rbac::ROLE_PHARMACIST,
                'service' => 'PHAR', 'speciality' => 'Pharmacie hospitalière',
            ],
            [
                'matricule' => 'REC-001', 'title' => 'Mme', 'first_name' => 'Mariam', 'last_name' => 'Camara',
                'email' => 'reception@keneya.test', 'role' => Rbac::ROLE_RECEPTION,
                'service' => 'ADM', 'speciality' => 'Accueil',
            ],
        ];

        foreach ($users as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'matricule' => $data['matricule'],
                    'title' => $data['title'],
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'name' => $data['first_name'].' '.$data['last_name'],
                    'speciality' => $data['speciality'],
                    'phone' => '+223 70 00 '.random_int(10, 99).' '.random_int(10, 99),
                    'service_id' => $services[$data['service']] ?? null,
                    'password' => $password,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$data['role']]);
        }

        $this->command?->info(count($users).' comptes de démonstration créés ou mis à jour.');
    }
}
