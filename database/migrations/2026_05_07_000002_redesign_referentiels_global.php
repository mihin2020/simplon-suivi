<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Step 1: Add referentiel_id to formations (nullable, no FK yet)
        if (!Schema::hasColumn('formations', 'referentiel_id')) {
            Schema::table('formations', function (Blueprint $table) {
                $table->uuid('referentiel_id')->nullable()->after('location');
            });
        }

        // Step 2: Migrate existing referentiel<->formation links
        if (Schema::hasColumn('referentiels', 'formation_id')) {
            DB::table('referentiels')->orderBy('id')->get()->each(function ($ref) {
                if (!empty($ref->formation_id)) {
                    DB::table('formations')
                        ->where('id', $ref->formation_id)
                        ->update(['referentiel_id' => $ref->id]);
                }
            });
        }

        // Step 3: Add FK constraint on formations.referentiel_id
        Schema::table('formations', function (Blueprint $table) {
            $table->foreign('referentiel_id')
                ->references('id')->on('referentiels')
                ->onDelete('set null');
        });

        // Step 4: Drop formation_id from referentiels.
        // On SQLite, the unique index must be dropped separately before the column.
        if (Schema::hasColumn('referentiels', 'formation_id')) {
            DB::statement('DROP INDEX IF EXISTS referentiels_formation_id_unique');
            Schema::table('referentiels', function (Blueprint $table) {
                $table->dropColumn('formation_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('referentiels', function (Blueprint $table) {
            $table->foreignUuid('formation_id')->nullable()->unique()->constrained()->onDelete('cascade');
        });

        Schema::table('formations', function (Blueprint $table) {
            $table->dropForeign(['referentiel_id']);
            $table->dropColumn('referentiel_id');
        });
    }
};
