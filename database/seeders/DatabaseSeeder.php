<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            EducationLevelSeeder::class,
            PermissionSeeder::class,
        ]);

        User::firstOrCreate(
            ['email' => 'superadmin@simplon.bf'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'password' => Hash::make('Simplon@2024!'),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );
    }
}
