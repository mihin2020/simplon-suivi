<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohort_learner', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('cohort_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('learner_id')->constrained()->onDelete('cascade');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->enum('status', ['actif', 'retrait', 'diplome'])->default('actif');

            $table->unique(['cohort_id', 'learner_id']);
            $table->index('learner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohort_learner');
    }
};
