<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trainer_trainer_profile', function (Blueprint $table) {
            $table->foreignUuid('trainer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('trainer_profile_id')->constrained()->cascadeOnDelete();

            $table->primary(['trainer_id', 'trainer_profile_id']);
        });

        // Keep the profile each trainer already had.
        DB::table('trainer_trainer_profile')->insertUsing(
            ['trainer_id', 'trainer_profile_id'],
            DB::table('trainers')->select('id', 'profile_id')->whereNotNull('profile_id'),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_trainer_profile');
    }
};
