<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campus_formation_id')->constrained()->onDelete('restrict');
            $table->string('name');
            $table->date('started_at');
            $table->date('ended_at');
            $table->unsignedSmallInteger('capacity')->default(30);
            $table->enum('status', ['planifiee', 'en_cours', 'cloturee'])->default('planifiee');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campus_formation_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cohorts');
    }
};
