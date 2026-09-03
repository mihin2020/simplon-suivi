<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\Phase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'phase_id' => Phase::factory(),
            'created_by' => User::factory()->superAdmin(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'started_at' => '2026-09-16',
            'ended_at' => '2026-09-25',
            'resources' => ['Salle', 'Ordinateur'],
            'position' => 1,
            'weight' => 1,
        ];
    }
}
