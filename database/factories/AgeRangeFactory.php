<?php

namespace Database\Factories;

use App\Models\AgeRange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgeRange>
 */
class AgeRangeFactory extends Factory
{
    public function definition(): array
    {
        $min = fake()->numberBetween(15, 40);

        return [
            'name' => "{$min}-".($min + 5).' ans',
            'age_min' => $min,
            'age_max' => $min + 5,
            'order' => fake()->numberBetween(1, 20),
        ];
    }
}
