<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract can be drafted before its recipient exists in the
     * application, so the polymorphic subject becomes optional.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('subject_type')->nullable()->change();
            $table->uuid('subject_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('subject_type')->nullable(false)->change();
            $table->uuid('subject_id')->nullable(false)->change();
        });
    }
};
