<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phases', function (Blueprint $table) {
            $table->string('priority')->nullable()->default(null)->change();
        });

        Schema::table('activities', function (Blueprint $table) {
            $table->string('priority')->nullable()->default(null)->change();
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('phases', fn (Blueprint $table) => $table->string('priority')->default('medium')->change());
        Schema::table('activities', fn (Blueprint $table) => $table->string('priority')->default('medium')->change());
        Schema::table('tasks', fn (Blueprint $table) => $table->string('priority')->default('medium')->change());
    }
};
