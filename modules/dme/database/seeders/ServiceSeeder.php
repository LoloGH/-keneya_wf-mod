<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Keneya\Dme\Models\Service;
use Illuminate\Database\Seeder;

/** Services de l'établissement de démonstration. */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['code' => 'MG', 'name' => 'Médecine générale', 'type' => 'clinical'],
            ['code' => 'MI', 'name' => 'Médecine interne', 'type' => 'clinical'],
            ['code' => 'CARD', 'name' => 'Cardiologie', 'type' => 'clinical'],
            ['code' => 'PED', 'name' => 'Pédiatrie', 'type' => 'clinical'],
            ['code' => 'GYN', 'name' => 'Gynécologie-obstétrique', 'type' => 'clinical'],
            ['code' => 'CHIR', 'name' => 'Chirurgie générale', 'type' => 'clinical'],
            ['code' => 'URG', 'name' => 'Urgences', 'type' => 'clinical'],
            ['code' => 'LAB', 'name' => 'Laboratoire d\'analyses', 'type' => 'medico_technical'],
            ['code' => 'IMG', 'name' => 'Imagerie médicale', 'type' => 'medico_technical'],
            ['code' => 'PHAR', 'name' => 'Pharmacie', 'type' => 'medico_technical'],
            ['code' => 'ADM', 'name' => 'Accueil et admissions', 'type' => 'administrative'],
        ];

        foreach ($services as $service) {
            Service::updateOrCreate(['code' => $service['code']], $service + ['is_active' => true]);
        }
    }
}
