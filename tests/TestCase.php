<?php

namespace Tests;

use App\Enums\UserRole;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // RefreshDatabase migrates once per run, then wraps each test in a transaction.
        $this->seedEssentialData();
    }

    protected function seedEssentialData(): void
    {
        // Créer les rôles de base si nécessaire
        $roles = ['admin', 'trainer', 'super-admin'];
        foreach ($roles as $role) {
            if (! Role::where('name', $role)->exists()) {
                Role::create(['name' => $role, 'slug' => $role]);
            }
        }
    }

    protected function createUser(?UserRole $role = null, bool $active = true): User
    {
        $role ??= UserRole::Trainer;

        return User::factory()->create([
            'role' => $role,
            'is_active' => $active,
        ]);
    }

    protected function createAdmin(bool $active = true): User
    {
        return $this->createUser(UserRole::Admin, $active);
    }

    protected function createSuperAdmin(bool $active = true): User
    {
        return $this->createUser(UserRole::SuperAdmin, $active);
    }

    protected function createTrainer(bool $active = true): User
    {
        return $this->createUser(UserRole::Trainer, $active);
    }

    protected function actingAsAdmin(): self
    {
        return $this->actingAs($this->createAdmin());
    }

    protected function actingAsSuperAdmin(): self
    {
        return $this->actingAs($this->createSuperAdmin());
    }

    protected function actingAsTrainer(): self
    {
        return $this->actingAs($this->createTrainer());
    }
}
