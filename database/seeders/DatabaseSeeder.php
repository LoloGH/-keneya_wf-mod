<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            // Apres RoleSeeder : les permissions du dossier medical se
            // rattachent aux roles que celui-ci vient de creer.
            DmePermissionSeeder::class,
            SettingSeeder::class,
            ServiceSeeder::class,
            DemoStaffSeeder::class,
        ]);
    }
}
