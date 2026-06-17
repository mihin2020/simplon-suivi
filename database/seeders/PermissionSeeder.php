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

            // Partenaires
            ['name' => 'Voir les partenaires',        'slug' => 'partners.view',    'group' => 'Partenaires'],
            ['name' => 'Créer un partenaire',         'slug' => 'partners.create',  'group' => 'Partenaires'],
            ['name' => 'Modifier un partenaire',      'slug' => 'partners.update',  'group' => 'Partenaires'],
            ['name' => 'Supprimer un partenaire',     'slug' => 'partners.delete',  'group' => 'Partenaires'],

            // Statistiques
            ['name' => 'Voir les statistiques',        'slug' => 'statistics.view',    'group' => 'Statistiques'],

            // Référentiels
            ['name' => 'Voir les référentiels',        'slug' => 'referentiels.view',    'group' => 'Référentiels'],
            ['name' => 'Créer un référentiel',         'slug' => 'referentiels.create',  'group' => 'Référentiels'],
            ['name' => 'Modifier un référentiel',      'slug' => 'referentiels.update',  'group' => 'Référentiels'],
            ['name' => 'Supprimer un référentiel',   'slug' => 'referentiels.delete',  'group' => 'Référentiels'],

            // Communication (Emails)
            ['name' => 'Voir la communication',       'slug' => 'communication.view',    'group' => 'Communication'],
            ['name' => 'Envoyer des emails',           'slug' => 'communication.send',    'group' => 'Communication'],
            ['name' => 'Gérer les emails (archiver, supprimer, synchroniser)', 'slug' => 'communication.manage', 'group' => 'Communication'],

            // WhatsApp
            ['name' => 'Voir les messages WhatsApp',   'slug' => 'whatsapp.view',    'group' => 'WhatsApp'],
            ['name' => 'Envoyer des messages WhatsApp','slug' => 'whatsapp.send',    'group' => 'WhatsApp'],
            ['name' => 'Gérer WhatsApp (synchroniser, déconnecter, supprimer)', 'slug' => 'whatsapp.manage', 'group' => 'WhatsApp'],

            // Dépenses
            ['name' => 'Voir les dépenses',           'slug' => 'expenses.view',    'group' => 'Dépenses'],
            ['name' => 'Ajouter une dépense',         'slug' => 'expenses.create',  'group' => 'Dépenses'],
            ['name' => 'Modifier une dépense',        'slug' => 'expenses.update',  'group' => 'Dépenses'],
            ['name' => 'Supprimer une dépense',       'slug' => 'expenses.delete',  'group' => 'Dépenses'],

            // Configuration
            ['name' => 'Voir la configuration',       'slug' => 'configuration.view',    'group' => 'Configuration'],
            ['name' => 'Gérer la configuration (référentiels de données, clé IA)', 'slug' => 'configuration.manage', 'group' => 'Configuration'],

            // Campus — Formations
            ['name' => 'Voir le catalogue formations',    'slug' => 'campus.formations.view',   'group' => 'Campus — Formations'],
            ['name' => 'Créer une formation',             'slug' => 'campus.formations.create', 'group' => 'Campus — Formations'],
            ['name' => 'Modifier une formation',          'slug' => 'campus.formations.update', 'group' => 'Campus — Formations'],
            ['name' => 'Supprimer une formation',         'slug' => 'campus.formations.delete', 'group' => 'Campus — Formations'],

            // Campus — Cohortes
            ['name' => 'Voir les cohortes',               'slug' => 'campus.cohorts.view',   'group' => 'Campus — Cohortes'],
            ['name' => 'Créer une cohorte',               'slug' => 'campus.cohorts.create', 'group' => 'Campus — Cohortes'],
            ['name' => 'Modifier une cohorte',            'slug' => 'campus.cohorts.update', 'group' => 'Campus — Cohortes'],
            ['name' => 'Clôturer une cohorte',            'slug' => 'campus.cohorts.close',  'group' => 'Campus — Cohortes'],
            ['name' => 'Supprimer une cohorte',           'slug' => 'campus.cohorts.delete', 'group' => 'Campus — Cohortes'],

            // Campus — Finance
            ['name' => 'Voir les paiements',              'slug' => 'campus.finance.view',   'group' => 'Campus — Finance'],
            ['name' => 'Encaisser un paiement',           'slug' => 'campus.finance.collect','group' => 'Campus — Finance'],
            ['name' => 'Gérer les échéanciers',           'slug' => 'campus.finance.manage', 'group' => 'Campus — Finance'],
            ['name' => 'Voir le tableau de bord financier','slug' => 'campus.finance.dashboard','group' => 'Campus — Finance'],

            // Campus — Workforce (gestion des apprenants campus)
            ['name' => 'Voir les apprenants de cohorte',  'slug' => 'campus.workforce.view',   'group' => 'Campus — Workforce'],
            ['name' => 'Inscrire un apprenant',           'slug' => 'campus.workforce.enroll', 'group' => 'Campus — Workforce'],
            ['name' => 'Retirer un apprenant',            'slug' => 'campus.workforce.remove', 'group' => 'Campus — Workforce'],
            ['name' => 'Déplacer un apprenant',           'slug' => 'campus.workforce.move',   'group' => 'Campus — Workforce'],
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
