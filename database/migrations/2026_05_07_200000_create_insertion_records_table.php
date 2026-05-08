<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('insertion_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('learner_id')->constrained('learners')->onDelete('cascade');
            
            // Statut d'insertion
            $table->string('status'); // searching, internship, employed, unemployed
            $table->timestamp('status_changed_at')->nullable();
            $table->text('status_notes')->nullable();
            
            // Informations de stage
            $table->date('internship_start_date')->nullable();
            $table->date('internship_end_date')->nullable();
            $table->string('internship_company')->nullable();
            $table->boolean('internship_paid')->nullable();
            $table->string('internship_contract_type')->nullable();
            
            // Informations d'emploi
            $table->string('employment_company')->nullable();
            $table->date('employment_start_date')->nullable();
            $table->string('employment_contract_type')->nullable(); // CDI, CDD, freelance, autre
            $table->string('employment_position')->nullable();
            
            // Suivi
            $table->foreignUuid('recorded_by')->nullable()->constrained('users');
            $table->timestamps();
            
            // Index
            $table->index('learner_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('insertion_records');
    }
};
