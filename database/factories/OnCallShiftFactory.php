<?php

namespace Database\Factories;

use App\Models\OnCallShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OnCallShiftFactory extends Factory
{
    protected $model = OnCallShift::class;

    public function definition()
    {
        return [
            'user_id' => User::factory()->dutyOfficer(),
            'start_at' => now(),
            'end_at' => now()->addHours(8),
        ];
    }
}
