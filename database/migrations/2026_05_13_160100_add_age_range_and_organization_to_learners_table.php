<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->unsignedBigInteger('age_range_id')->nullable()->after('birth_date');
            $table->string('organization')->nullable()->after('profile');

            $table->foreign('age_range_id')
                ->references('id')->on('age_ranges')
                ->onDelete('set null');
            $table->index('age_range_id');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropForeign(['age_range_id']);
            $table->dropIndex(['age_range_id']);
            $table->dropColumn(['age_range_id', 'organization']);
        });
    }
};
