<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->foreignUuid('profile_id')
                ->nullable()
                ->after('user_id')
                ->constrained('trainer_profiles')
                ->onDelete('set null');

            $table->string('phone2')->nullable()->after('phone');
            $table->string('cv_path')->nullable()->after('phone2');
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
            $table->dropColumn(['profile_id', 'phone2', 'cv_path']);
        });
    }
};
