<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Formation;
use App\Models\Permission;
use App\Models\User;
use App\Rules\CloudinaryUrl;
use App\Services\AiTools;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    /** @param  array<int, string>  $slugs */
    private function adminWith(array $slugs): User
    {
        $admin = $this->createAdmin();

        foreach ($slugs as $slug) {
            $permission = Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'group' => 'Test']);
            $admin->permissions()->attach($permission->id);
        }

        return $admin;
    }

    private function userPayload(User $target, array $overrides = []): array
    {
        return [
            'first_name' => $target->first_name,
            'last_name' => $target->last_name,
            'email' => $target->email,
            'role' => UserRole::Admin->value,
            ...$overrides,
        ];
    }

    public function test_user_cannot_change_own_permissions_through_the_user_form(): void
    {
        $admin = $this->adminWith(['users.update']);
        $other = Permission::firstOrCreate(['slug' => 'backup.manage'], ['name' => 'backup.manage', 'group' => 'Test']);

        $this->actingAs($admin)
            ->put(route('users.update', $admin), $this->userPayload($admin, ['permissions' => [$other->id]]))
            ->assertForbidden();

        $this->assertFalse($admin->fresh()->hasPermission('backup.manage'));
    }

    public function test_trainer_cannot_edit_own_account_through_the_user_form(): void
    {
        $trainer = $this->createTrainer();

        $this->actingAs($trainer)
            ->put(route('users.update', $trainer), $this->userPayload($trainer))
            ->assertForbidden();
    }

    public function test_admin_cannot_edit_the_super_admin(): void
    {
        $superAdmin = $this->createSuperAdmin();
        $admin = $this->adminWith(['users.update']);

        $this->actingAs($admin)
            ->put(route('users.update', $superAdmin), $this->userPayload($superAdmin, ['email' => 'attacker@example.test']))
            ->assertForbidden();

        $this->assertNotSame('attacker@example.test', $superAdmin->fresh()->email);
        $this->assertSame(UserRole::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_super_admin_cannot_be_demoted_through_the_user_form(): void
    {
        $superAdmin = $this->createSuperAdmin();

        $this->actingAs($superAdmin)
            ->put(route('users.update', $superAdmin), $this->userPayload($superAdmin))
            ->assertForbidden();

        $this->assertSame(UserRole::SuperAdmin, $superAdmin->fresh()->role);
    }

    public function test_admin_can_edit_another_admin_with_the_users_update_permission(): void
    {
        $admin = $this->adminWith(['users.update']);
        $target = $this->createAdmin();

        $this->actingAs($admin)
            ->put(route('users.update', $target), $this->userPayload($target, ['first_name' => 'Renamed']))
            ->assertRedirect(route('users.index'));

        $this->assertSame('Renamed', $target->fresh()->first_name);
    }

    public function test_admin_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $admin = $this->adminWith(['users.create']);
        $foreign = Permission::firstOrCreate(['slug' => 'backup.manage'], ['name' => 'backup.manage', 'group' => 'Test']);

        $this->actingAs($admin)
            ->post(route('users.store'), [
                'first_name' => 'New',
                'last_name' => 'Admin',
                'email' => 'new.admin@example.test',
                'role' => UserRole::Admin->value,
                'permissions' => [$foreign->id],
            ])
            ->assertSessionHasErrors('permissions');

        $this->assertDatabaseMissing('users', ['email' => 'new.admin@example.test']);
    }

    public function test_media_url_must_come_from_cloudinary(): void
    {
        $formation = Formation::factory()->create();
        $this->actingAsSuperAdmin();

        foreach (['file:///etc/passwd', 'http://169.254.169.254/latest/meta-data', 'https://evil.example/a.jpg', 'https://res.cloudinary.com.evil.example/a.jpg'] as $url) {
            $this->postJson(route('formations.medias.store', $formation), [
                'cloudinary_public_id' => 'formations/x',
                'url' => $url,
                'type' => 'photo',
            ])->assertUnprocessable()->assertJsonValidationErrors('url');
        }

        $this->assertDatabaseCount('medias', 0);
    }

    public function test_cloudinary_url_rule(): void
    {
        $this->assertTrue(CloudinaryUrl::isValid('https://res.cloudinary.com/demo/image/upload/a.jpg'));
        $this->assertFalse(CloudinaryUrl::isValid('http://res.cloudinary.com/demo/a.jpg'));
        $this->assertFalse(CloudinaryUrl::isValid('https://user:pw@res.cloudinary.com/demo/a.jpg'));
        $this->assertFalse(CloudinaryUrl::isValid('https://res.cloudinary.com:8443/demo/a.jpg'));
        $this->assertFalse(CloudinaryUrl::isValid(null));
    }

    public function test_learner_import_requires_the_create_permission(): void
    {
        $this->actingAs($this->adminWith([]))->get(route('learners.import'))->assertForbidden();
        $this->actingAs($this->adminWith([]))->post(route('learners.import.store'))->assertForbidden();
        $this->actingAs($this->adminWith([]))->get(route('learners.import.template'))->assertForbidden();

        $this->actingAs($this->adminWith(['learners.create']))->get(route('learners.import'))->assertOk();
    }

    public function test_learner_search_requires_a_communication_permission(): void
    {
        $this->actingAs($this->createTrainer())->getJson(route('api.learners.search'))->assertForbidden();
        $this->actingAs($this->adminWith(['learners.view']))->getJson(route('api.learners.search'))->assertForbidden();
        $this->actingAs($this->adminWith(['communication.send']))->getJson(route('api.learners.search'))->assertOk();
    }

    public function test_cloudinary_stats_requires_formations_view(): void
    {
        $this->actingAs($this->createTrainer())->getJson(route('api.cloudinary.stats'))->assertForbidden();
    }

    public function test_ai_assistant_requires_configuration_access(): void
    {
        $this->actingAs($this->createTrainer())->getJson(route('chatbot.status'))->assertForbidden();
        $this->actingAs($this->createTrainer())->postJson(route('chatbot.message'), ['message' => 'Bonjour'])->assertForbidden();
        $this->actingAs($this->adminWith([]))->getJson(route('chatbot.status'))->assertForbidden();

        $this->actingAs($this->adminWith(['configuration.view']))->getJson(route('chatbot.status'))->assertOk();
    }

    public function test_ai_tools_follow_the_users_permissions(): void
    {
        $this->actingAs($this->adminWith(['configuration.view', 'learners.view']));

        $names = array_column(AiTools::definitions(), 'name');
        sort($names);

        $this->assertSame(['get_learner_detail', 'list_insertions', 'list_reference_data', 'search_learners'], $names);
        $this->assertStringContainsString('Accès refusé', AiTools::execute('list_users', [])['error']);
    }

    public function test_ai_tools_give_the_super_admin_everything(): void
    {
        $this->actingAsSuperAdmin();

        $this->assertCount(21, AiTools::definitions());
    }
}
