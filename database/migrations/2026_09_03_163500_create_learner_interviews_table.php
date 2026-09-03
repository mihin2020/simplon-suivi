<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_interviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('learner_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('conducted_by')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->date('conducted_at');
            $table->string('subject');
            $table->text('notes')->nullable();
            $table->text('recommendation')->nullable();
            $table->date('next_follow_up_at')->nullable();
            $table->boolean('is_important')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['learner_id', 'conducted_at']);
            $table->index(['learner_id', 'next_follow_up_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_interviews');
    }
};
