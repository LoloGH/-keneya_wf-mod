<?php

namespace Database\Factories;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    protected $model = Schedule::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'date' => today(),
            'start_time' => '08:00',
            'end_time' => '14:00',
            'service_id' => null,
        ];
    }
}
