<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('formation_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('learner_id')->constrained()->onDelete('restrict');
            $table->date('date');
            $table->string('code', 2)->default('P'); // P, AJ, AN, RJ, RN
            $table->text('comment')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();

            $table->unique(['formation_id', 'learner_id', 'date']);
            $table->index(['formation_id', 'date']);
            $table->index('learner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
