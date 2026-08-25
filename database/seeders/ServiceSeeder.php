<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

/**
 * Les six services de depart de l'Hopital Fousseyni Daou de Kayes.
 */
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Medecine Generale', 'kind' => Service::KIND_CLINIQUE],
            ['name' => 'Urgences', 'kind' => Service::KIND_CLINIQUE],
            ['name' => 'Maternite', 'kind' => Service::KIND_CLINIQUE],
            ['name' => 'Administration', 'kind' => Service::KIND_CLINIQUE],
            ['name' => 'Echographie', 'kind' => Service::KIND_PLATEAU_TECHNIQUE],
            ['name' => 'Laboratoire', 'kind' => Service::KIND_PLATEAU_TECHNIQUE],

            // Les deux caisses sont des services a part entiere : meme file,
            // meme token, meme « Appeler le suivant ».
            ['name' => Service::CAISSE_TICKET, 'kind' => Service::KIND_CAISSE],
            ['name' => Service::CAISSE_SERVICES, 'kind' => Service::KIND_CAISSE],
        ];

        foreach ($services as $service) {
            Service::firstOrCreate(['name' => $service['name']], $service);
        }
    }
}
