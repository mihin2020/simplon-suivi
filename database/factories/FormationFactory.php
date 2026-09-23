<?php

namespace Database\Factories;

use App\Enums\FormationStatus;
use App\Models\Formation;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Formation>
 */
class FormationFactory extends Factory
{
    protected $model = Formation::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'name' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'started_at' => '2026-09-01',
            'ended_at' => '2026-12-31',
            'status' => FormationStatus::Active,
            'capacity' => 30,
            'location' => 'Ouagadougou',
        ];
    }
}
