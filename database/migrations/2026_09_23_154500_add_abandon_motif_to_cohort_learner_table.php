<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cohort_learner', function (Blueprint $table) {
            $table->string('abandon_motif', 500)->nullable()->after('status');
            $table->timestamp('abandoned_at')->nullable()->after('abandon_motif');
        });
    }

    public function down(): void
    {
        Schema::table('cohort_learner', function (Blueprint $table) {
            $table->dropColumn(['abandon_motif', 'abandoned_at']);
        });
    }
};
