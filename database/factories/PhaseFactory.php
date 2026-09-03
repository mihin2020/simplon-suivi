<?php

namespace Database\Factories;

use App\Models\Phase;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Phase>
 */
class PhaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'created_by' => User::factory()->superAdmin(),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'started_at' => '2026-09-15',
            'ended_at' => '2026-09-30',
            'position' => 1,
            'weight' => 1,
        ];
    }
}
