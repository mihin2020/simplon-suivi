<?php

namespace Database\Seeders;

use App\Models\EducationLevel;
use Illuminate\Database\Seeder;

class EducationLevelSeeder extends Seeder
{
    public function run(): void
    {
        $levels = [
            ['name' => 'Infrabac',      'order' => 1],
            ['name' => 'Bac+2',        'order' => 2],
            ['name' => 'Bac+3 et plus', 'order' => 3],
        ];

        foreach ($levels as $level) {
            EducationLevel::firstOrCreate(['name' => $level['name']], $level);
        }
    }
}
