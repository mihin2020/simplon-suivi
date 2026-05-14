<?php

namespace Database\Seeders;

use App\Models\AgeRange;
use Illuminate\Database\Seeder;

class AgeRangeSeeder extends Seeder
{
    public function run(): void
    {
        $ranges = [
            ['age_min' => 5,  'age_max' => 12,  'order' => 1],
            ['age_min' => 13, 'age_max' => 17,  'order' => 2],
            ['age_min' => 18, 'age_max' => 25,  'order' => 3],
            ['age_min' => 26, 'age_max' => 45,  'order' => 4],
            ['age_min' => 46, 'age_max' => 69,  'order' => 5],
            ['age_min' => 70, 'age_max' => 150, 'order' => 6],
        ];

        foreach ($ranges as $range) {
            $range['name'] = $range['age_max'] >= 150
                ? "{$range['age_min']} ans et +"
                : "{$range['age_min']} - {$range['age_max']} ans";

            AgeRange::firstOrCreate(
                ['age_min' => $range['age_min'], 'age_max' => $range['age_max']],
                $range
            );
        }
    }
}
