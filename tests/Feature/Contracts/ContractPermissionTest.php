<?php

namespace Tests\Feature\Contracts;

use App\Enums\ContractTarget;
use App\Models\Contract;
use App\Models\ContractProject;
use App\Models\Permission;
use App\Models\User;
use Tests\TestCase;

/**
 * Access to the contracts module is granted per user, from the "Contrats"
 * permissions the Super Admin ticks on the user's page.
 */
class ContractPermissionTest extends TestCase
{
    use ContractTestHelpers;

    private function adminWith(string ...$slugs): User
    {
        $admin = $this->createAdmin();
        $admin->permissions()->sync(Permission::whereIn('slug', $slugs)->pluck('id'));

        return $admin;
    }

    public function test_the_contract_permissions_are_offered_on_the_user_page(): void
    {
        $this->assertSame(
            ['contracts.create', 'contracts.delete', 'contracts.templates', 'contracts.update', 'contracts.view'],
            Permission::where('group', 'Contrats')->orderBy('slug')->pluck('slug')->all(),
        );
    }

    public function test_an_admin_without_contract_permissions_cannot_reach_the_module(): void
    {
        $admin = $this->adminWith();

        $this->actingAs($admin)->get('/contracts')->assertForbidden();
        $this->actingAs($admin)->get('/contracts/projects')->assertForbidden();
    }

    public function test_view_permission_only_allows_reading(): void
    {
        $admin = $this->adminWith('contracts.view');
        $contract = $this->contract();
        $project = ContractProject::create(['name' => 'Cohorte', 'target' => ContractTarget::Trainer]);

        $this->actingAs($admin)->get('/contracts')->assertOk();
        $this->actingAs($admin)->get('/contracts/projects')->assertOk();
        $this->actingAs($admin)->get("/contracts/{$contract->id}")->assertOk();

        $this->actingAs($admin)->post("/contracts/{$contract->id}/copy")->assertForbidden();
        $this->actingAs($admin)->get("/contracts/{$contract->id}/edit")->assertForbidden();
        $this->actingAs($admin)->delete("/contracts/{$contract->id}")->assertForbidden();
        $this->actingAs($admin)->delete("/contracts/projects/{$project->id}")->assertForbidden();
        $this->actingAs($admin)->get('/contracts/layouts')->assertForbidden();

        $this->assertSame(1, Contract::count());
        $this->assertNotSoftDeleted($project);
    }

    public function test_create_permission_allows_duplicating(): void
    {
        $admin = $this->adminWith('contracts.view', 'contracts.create');
        $contract = $this->contract();

        // Without the right to modify, the copy opens read-only.
        $copy = fn () => Contract::whereKeyNot($contract->id)->latest()->firstOrFail();
        $this->actingAs($admin)->post("/contracts/{$contract->id}/copy")
            ->assertRedirect("/contracts/{$copy()->id}");
    }

    public function test_delete_permission_allows_deleting_a_project_with_its_contracts(): void
    {
        $admin = $this->adminWith('contracts.view', 'contracts.delete');
        $project = ContractProject::create(['name' => 'Cohorte', 'target' => ContractTarget::Trainer]);
        $contracts = [$this->contract(['contract_project_id' => $project->id]), $this->contract(['contract_project_id' => $project->id])];

        $this->actingAs($admin)->delete("/contracts/projects/{$project->id}")
            ->assertRedirect('/contracts/projects')
            ->assertSessionHas('success', 'Projet supprimé, ainsi que ses 2 contrats.');

        $this->assertSoftDeleted($project);
        foreach ($contracts as $contract) {
            $this->assertSoftDeleted($contract);
        }
    }

    public function test_the_super_admin_needs_no_explicit_permission(): void
    {
        $project = ContractProject::create(['name' => 'Cohorte', 'target' => ContractTarget::Trainer]);

        $this->actingAsSuperAdmin()->delete("/contracts/projects/{$project->id}")->assertRedirect('/contracts/projects');

        $this->assertSoftDeleted($project);
    }
}
