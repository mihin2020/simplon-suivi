<?php

namespace Database\Factories;

use App\Models\Learner;
use App\Models\LearnerInterview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LearnerInterview>
 */
class LearnerInterviewFactory extends Factory
{
    public function definition(): array
    {
        return [
            'learner_id' => Learner::factory(),
            'conducted_by' => User::factory()->superAdmin(),
            'created_by' => fn (array $attributes) => $attributes['conducted_by'],
            'conducted_at' => now()->toDateString(),
            'subject' => 'Point de suivi pédagogique',
            'notes' => fake()->sentence(),
            'recommendation' => fake()->sentence(),
            'next_follow_up_at' => now()->addWeek()->toDateString(),
            'is_important' => false,
            'meta' => null,
        ];
    }
}
