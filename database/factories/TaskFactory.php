<?php

namespace Database\Factories;

use App\Enums\TaskStatus;
use App\Models\Activity;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Task>
 */
class TaskFactory extends Factory
{
    public function definition(): array
    {
        return [
            'activity_id' => Activity::factory(),
            'created_by' => User::factory()->superAdmin(),
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'started_at' => '2026-09-16',
            'ended_at' => '2026-09-18',
            'status' => TaskStatus::Todo,
            'position' => 1,
        ];
    }

    public function done(): static
    {
        return $this->state(fn () => [
            'status' => TaskStatus::Done,
            'completed_at' => now(),
        ]);
    }
}
