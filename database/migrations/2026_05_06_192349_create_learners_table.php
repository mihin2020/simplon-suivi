<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learners', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique()->nullable();
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('birth_place')->nullable();
            $table->string('gender')->nullable();
            $table->unsignedBigInteger('education_level_id')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('talent')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_firstname')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('education_level_id')
                ->references('id')->on('education_levels')
                ->onDelete('set null');
            $table->index('email');
            $table->index('education_level_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learners');
    }
};
