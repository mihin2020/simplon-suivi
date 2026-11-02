<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The migration that made referentiels global skips the removal of formation_id on SQLite, so the test database kept
 * a required column the application no longer fills. MySQL already dropped it: this does nothing there.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite' || ! Schema::hasColumn('referentiels', 'formation_id')) {
            return;
        }

        // SQLite cannot drop a column that an index or a foreign key still references.
        Schema::table('referentiels', function (Blueprint $table) {
            $table->dropForeign(['formation_id']);
        });

        Schema::table('referentiels', function (Blueprint $table) {
            $table->dropUnique('referentiels_formation_id_unique');
        });

        Schema::table('referentiels', function (Blueprint $table) {
            $table->dropColumn('formation_id');
        });
    }

    public function down(): void
    {
        // The column was obsolete: nothing to restore.
    }
};
