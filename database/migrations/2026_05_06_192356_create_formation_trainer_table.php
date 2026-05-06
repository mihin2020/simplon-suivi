<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_trainer', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('formation_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('trainer_id')->constrained()->onDelete('restrict');
            $table->boolean('is_lead')->default(false);
            $table->timestamp('assigned_at')->useCurrent();

            $table->unique(['formation_id', 'trainer_id']);
            $table->index('formation_id');
            $table->index('trainer_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_trainer');
    }
};
