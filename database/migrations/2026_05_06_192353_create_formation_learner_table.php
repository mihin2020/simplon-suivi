<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_learner', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('formation_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('learner_id')->constrained()->onDelete('restrict');
            $table->string('status')->default('in_progress');
            $table->timestamp('enrolled_at')->useCurrent();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->unique(['formation_id', 'learner_id']);
            $table->index('status');
            $table->index('formation_id');
            $table->index('learner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_learner');
    }
};
