<?php

namespace Database\Seeders;

use App\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // Users
            ['name' => 'Voir les utilisateurs',       'slug' => 'users.view'],
            ['name' => 'Créer un utilisateur',        'slug' => 'users.create'],
            ['name' => 'Modifier un utilisateur',     'slug' => 'users.update'],
            ['name' => 'Supprimer un utilisateur',    'slug' => 'users.delete'],

            // Projects
            ['name' => 'Voir les projets',            'slug' => 'projects.view'],
            ['name' => 'Créer un projet',             'slug' => 'projects.create'],
            ['name' => 'Modifier un projet',          'slug' => 'projects.update'],
            ['name' => 'Supprimer un projet',         'slug' => 'projects.delete'],

            // Formations
            ['name' => 'Voir les formations',         'slug' => 'formations.view'],
            ['name' => 'Créer une formation',         'slug' => 'formations.create'],
            ['name' => 'Modifier une formation',      'slug' => 'formations.update'],
            ['name' => 'Supprimer une formation',     'slug' => 'formations.delete'],

            // Learners
            ['name' => 'Voir les apprenants',         'slug' => 'learners.view'],
            ['name' => 'Créer un apprenant',          'slug' => 'learners.create'],
            ['name' => 'Modifier un apprenant',       'slug' => 'learners.update'],
            ['name' => 'Supprimer un apprenant',      'slug' => 'learners.delete'],
            ['name' => 'Déplacer un apprenant',       'slug' => 'learners.move'],

            // Trainers
            ['name' => 'Voir les formateurs',         'slug' => 'trainers.view'],
            ['name' => 'Créer un formateur',          'slug' => 'trainers.create'],
            ['name' => 'Modifier un formateur',       'slug' => 'trainers.update'],
            ['name' => 'Supprimer un formateur',      'slug' => 'trainers.delete'],

            // Attendances
            ['name' => 'Voir les présences',          'slug' => 'attendances.view'],
            ['name' => 'Saisir les présences',        'slug' => 'attendances.create'],
            ['name' => 'Modifier les présences',      'slug' => 'attendances.update'],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['slug' => $permission['slug']], $permission);
        }
    }
}
