<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Utilisateurs
            ['name' => 'Voir les utilisateurs',       'slug' => 'users.view',       'group' => 'Utilisateurs'],
            ['name' => 'Créer un utilisateur',        'slug' => 'users.create',     'group' => 'Utilisateurs'],
            ['name' => 'Modifier un utilisateur',     'slug' => 'users.update',     'group' => 'Utilisateurs'],
            ['name' => 'Supprimer un utilisateur',    'slug' => 'users.delete',     'group' => 'Utilisateurs'],

            // Projets
            ['name' => 'Voir les projets',            'slug' => 'projects.view',    'group' => 'Projets'],
            ['name' => 'Créer un projet',             'slug' => 'projects.create',  'group' => 'Projets'],
            ['name' => 'Modifier un projet',          'slug' => 'projects.update',  'group' => 'Projets'],
            ['name' => 'Supprimer un projet',         'slug' => 'projects.delete',  'group' => 'Projets'],

            // Formations
            ['name' => 'Voir les formations',         'slug' => 'formations.view',    'group' => 'Formations'],
            ['name' => 'Créer une formation',         'slug' => 'formations.create',  'group' => 'Formations'],
            ['name' => 'Modifier une formation',      'slug' => 'formations.update',  'group' => 'Formations'],
            ['name' => 'Supprimer une formation',     'slug' => 'formations.delete',  'group' => 'Formations'],

            // Apprenants
            ['name' => 'Voir les apprenants',         'slug' => 'learners.view',    'group' => 'Apprenants'],
            ['name' => 'Créer un apprenant',          'slug' => 'learners.create',  'group' => 'Apprenants'],
            ['name' => 'Modifier un apprenant',       'slug' => 'learners.update',  'group' => 'Apprenants'],
            ['name' => 'Supprimer un apprenant',      'slug' => 'learners.delete',  'group' => 'Apprenants'],
            ['name' => 'Déplacer un apprenant',       'slug' => 'learners.move',    'group' => 'Apprenants'],

            // Formateurs
            ['name' => 'Voir les formateurs',         'slug' => 'trainers.view',    'group' => 'Formateurs'],
            ['name' => 'Créer un formateur',          'slug' => 'trainers.create',  'group' => 'Formateurs'],
            ['name' => 'Modifier un formateur',       'slug' => 'trainers.update',  'group' => 'Formateurs'],
            ['name' => 'Supprimer un formateur',      'slug' => 'trainers.delete',  'group' => 'Formateurs'],

            // Présences
            ['name' => 'Voir les présences',          'slug' => 'attendances.view',   'group' => 'Présences'],
            ['name' => 'Saisir les présences',        'slug' => 'attendances.create', 'group' => 'Présences'],
            ['name' => 'Modifier les présences',      'slug' => 'attendances.update', 'group' => 'Présences'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Rôles système
        $adminRole = Role::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrateur']
        );

        $trainerRole = Role::firstOrCreate(
            ['slug' => 'trainer'],
            ['name' => 'Formateur']
        );

        // Administrateur : toutes les permissions par défaut
        $adminPermissions = Permission::pluck('id');
        $adminRole->permissions()->sync($adminPermissions);

        // Formateur : uniquement les présences
        $trainerSlugs = ['attendances.view', 'attendances.create', 'attendances.update'];
        $trainerPermissionIds = Permission::whereIn('slug', $trainerSlugs)->pluck('id');
        $trainerRole->permissions()->sync($trainerPermissionIds);
    }
}
