<?php

namespace Database\Factories;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EducationLevel>
 */
class EducationLevelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['CEP', 'BEPC', 'BAC', 'BAC+2', 'Licence', 'Master']).' '.fake()->unique()->numberBetween(1, 9999),
            'order' => fake()->numberBetween(1, 20),
        ];
    }
}
