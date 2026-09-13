<?php

declare(strict_types=1);

namespace Keneya\Dme\Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Amorçage du module.
 *
 * Porte un nom propre au module : l'application hôte a son propre
 * DatabaseSeeder, qu'il n'est pas question de remplacer. Elle appelle
 * celui-ci explicitement :
 *
 *   php artisan db:seed --class="Keneya\Dme\Database\Seeders\DmeSeeder"
 *
 * Les trois premiers seeders sont structurels et s'exécutent dans tous
 * les environnements. Les deux suivants ne créent des données qu'en local
 * et en test (§55).
 */
class DmeSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleAndPermissionSeeder::class,
            ServiceSeeder::class,
            SmsTemplateSeeder::class,
            DemoUserSeeder::class,
            DemoMedicalDataSeeder::class,
        ]);
    }
}
