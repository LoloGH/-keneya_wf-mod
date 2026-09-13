<?php

declare(strict_types=1);

namespace Workbench\Database\Seeders;

use Illuminate\Database\Seeder;
use Keneya\Dme\Database\Seeders\DmeSeeder;
use Keneya\Dme\Database\Seeders\StandaloneDevSeeder;

/**
 * Amorçage de l'application hôte de test.
 *
 * L'hôte appelle le seeder du module comme il appellerait celui de
 * n'importe quel package, puis ajoute le praticien de développement qui
 * permet d'ouvrir une session sans Keneya Workflow.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DmeSeeder::class,
            StandaloneDevSeeder::class,
        ]);
    }
}
