<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The contracts module's permissions, for databases already in production.
     *
     * PermissionSeeder declares them too, but running it on a live database
     * resets the Administrator role to every permission (sync) and would undo
     * the choices made online. This only adds what is missing.
     */
    private const PERMISSIONS = [
        ['name' => 'Voir les contrats', 'slug' => 'contracts.view'],
        ['name' => 'Générer un contrat', 'slug' => 'contracts.create'],
        ['name' => 'Modifier un contrat', 'slug' => 'contracts.update'],
        ['name' => 'Supprimer un contrat', 'slug' => 'contracts.delete'],
        ['name' => 'Gérer les modèles de contrat', 'slug' => 'contracts.templates'],
    ];

    public function up(): void
    {
        $now = now();

        foreach (self::PERMISSIONS as $permission) {
            if (! DB::table('permissions')->where('slug', $permission['slug'])->exists()) {
                DB::table('permissions')->insert($permission + ['group' => 'Contrats', 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        // The Administrator role has every permission by default: give it the
        // new ones too, without touching the rest of its permissions.
        $adminRoleId = DB::table('roles')->where('slug', 'admin')->value('id');
        if ($adminRoleId === null) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', array_column(self::PERMISSIONS, 'slug'))->pluck('id');
        foreach ($permissionIds as $permissionId) {
            DB::table('role_permission')->insertOrIgnore(['role_id' => $adminRoleId, 'permission_id' => $permissionId]);
        }
    }

    public function down(): void
    {
        // Kept: removing permissions would also remove them from every user and role.
    }
};
