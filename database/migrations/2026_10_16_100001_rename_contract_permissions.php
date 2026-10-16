<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Labels shown to the Super Admin when granting rights: they now say what
     * each permission really allows (deleting a project deletes its contracts).
     * Only the names change; who holds which permission is untouched.
     */
    private const NAMES = [
        'contracts.view' => ['Voir les contrats', 'Voir les contrats et les projets'],
        'contracts.create' => ['Générer un contrat', 'Créer, dupliquer et générer des contrats'],
        'contracts.update' => ['Modifier un contrat', 'Modifier et finaliser les contrats et les projets'],
        'contracts.delete' => ['Supprimer un contrat', 'Supprimer des contrats et des projets (avec leurs contrats)'],
        'contracts.templates' => ['Gérer les modèles de contrat', 'Gérer les en-têtes et pieds de page'],
    ];

    public function up(): void
    {
        foreach (self::NAMES as $slug => [, $name]) {
            DB::table('permissions')->where('slug', $slug)->update(['name' => $name, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::NAMES as $slug => [$name]) {
            DB::table('permissions')->where('slug', $slug)->update(['name' => $name, 'updated_at' => now()]);
        }
    }
};
