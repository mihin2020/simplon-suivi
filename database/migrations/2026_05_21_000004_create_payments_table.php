<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('cohort_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('learner_id')->constrained()->onDelete('restrict');
            $table->unsignedBigInteger('amount')->comment('En FCFA');
            $table->unsignedTinyInteger('installment_number')->comment('Numéro de tranche');
            $table->date('due_date')->comment('Date limite de paiement');
            $table->date('paid_at')->nullable()->comment('Date de paiement effectif');
            $table->enum('status', ['en_attente', 'paye', 'en_retard', 'annule'])->default('en_attente');
            $table->string('reference')->nullable()->comment('Référence reçu');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['cohort_id', 'learner_id']);
            $table->index(['status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
