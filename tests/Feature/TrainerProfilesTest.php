<?php

namespace Tests\Feature;

use App\Models\Trainer;
use App\Models\TrainerProfile;
use App\Models\User;
use Tests\TestCase;

class TrainerProfilesTest extends TestCase
{
    public function test_a_trainer_can_be_created_with_several_profiles(): void
    {
        $profiles = collect(['Développeur web', 'Data analyst', 'Soft skills'])
            ->map(fn (string $name) => TrainerProfile::create(['name' => $name]));

        $this->actingAsSuperAdmin()->post(route('users.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Zongo',
            'email' => 'awa.zongo@example.test',
            'role' => 'trainer',
            'profile_ids' => $profiles->take(2)->pluck('id')->all(),
        ])->assertRedirect(route('users.index'));

        $trainer = Trainer::whereHas('user', fn ($q) => $q->where('email', 'awa.zongo@example.test'))->firstOrFail();

        $this->assertEqualsCanonicalizing(
            $profiles->take(2)->pluck('id')->all(),
            $trainer->profiles()->pluck('trainer_profiles.id')->all(),
        );
    }

    public function test_profiles_can_be_replaced_and_cleared(): void
    {
        $a = TrainerProfile::create(['name' => 'Développeur web']);
        $b = TrainerProfile::create(['name' => 'Data analyst']);

        $user = User::factory()->create(['role' => 'trainer']);
        $trainer = Trainer::create(['user_id' => $user->id]);
        $trainer->profiles()->sync([$a->id]);

        $payload = [
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'role' => 'trainer',
        ];

        $this->actingAsSuperAdmin()
            ->put(route('users.update', $user), $payload + ['profile_ids' => [$a->id, $b->id]])
            ->assertRedirect(route('users.index'));
        $this->assertSame(2, $trainer->profiles()->count());

        $this->put(route('users.update', $user), $payload + ['profile_ids' => []])->assertRedirect();
        $this->assertSame(0, $trainer->profiles()->count());
    }

    public function test_unknown_profile_is_rejected(): void
    {
        $this->actingAsSuperAdmin()->post(route('users.store'), [
            'first_name' => 'Awa',
            'last_name' => 'Zongo',
            'email' => 'awa.zongo@example.test',
            'role' => 'trainer',
            'profile_ids' => ['01a1773b-f314-71b6-a27c-1eadc1291270'],
        ])->assertSessionHasErrors('profile_ids.0');
    }
}
