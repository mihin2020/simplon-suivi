<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campus_formation_installments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('campus_formation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->enum('type', ['percentage', 'amount']);
            $table->unsignedBigInteger('value')->comment('Pourcentage (1-100) ou montant FCFA');
            $table->date('due_date');
            $table->timestamps();

            $table->unique(['campus_formation_id', 'position'], 'cfi_formation_position_unique');
            $table->index('campus_formation_id', 'cfi_formation_id_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campus_formation_installments');
    }
};
