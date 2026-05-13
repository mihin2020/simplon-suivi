<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->text('address')->nullable()->after('emergency_contact_phone');
            $table->string('location')->nullable()->after('address');
            $table->string('profile')->nullable()->after('location');
            $table->string('study_field')->nullable()->after('profile');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropColumn(['address', 'location', 'profile', 'study_field']);
        });
    }
};
