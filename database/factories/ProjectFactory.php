<?php

namespace Database\Factories;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'budget' => 1_000_000,
            'started_at' => '2026-09-01',
            'ended_at' => '2026-12-31',
            'status' => ProjectStatus::Active,
        ];
    }
}
