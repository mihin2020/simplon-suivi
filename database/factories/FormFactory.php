<?php

namespace Database\Factories;

use App\Enums\FormStatus;
use App\Models\Form;
use App\Models\Formation;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Form>
 */
class FormFactory extends Factory
{
    protected $model = Form::class;

    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'formation_id' => function (array $attributes) {
                return Formation::factory()->create([
                    'project_id' => $attributes['project_id'],
                ])->id;
            },
            'created_by' => User::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'status' => FormStatus::Draft,
            'public_token' => Form::generatePublicToken(),
            'settings' => Form::defaultSettings(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => FormStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => FormStatus::Closed,
            'closed_at' => now(),
            'published_at' => now()->subDay(),
            'settings' => array_merge(Form::defaultSettings(), ['accept_responses' => false]),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => FormStatus::Locked,
            'locked_at' => now(),
            'closed_at' => now(),
            'published_at' => now()->subDay(),
            'settings' => array_merge(Form::defaultSettings(), ['accept_responses' => false]),
        ]);
    }
}
