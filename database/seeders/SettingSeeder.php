<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        Setting::firstOrCreate(
            ['key' => Setting::HOSPITAL_NAME],
            ['value' => 'Hopital Fousseyni Daou'],
        );
    }
}
