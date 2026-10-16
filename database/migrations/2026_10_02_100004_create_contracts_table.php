<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('reference')->unique();
            $table->foreignUuid('contract_template_id')->nullable()
                ->constrained('contract_templates')->nullOnDelete();
            $table->string('target'); // ContractTarget enum (snapshot, template may be deleted)

            // Polymorphic subject: Trainer, Cme or Partner
            $table->uuidMorphs('subject');

            $table->string('title');
            $table->longText('body_html')->nullable();   // editable, frozen copy of the merged template
            $table->longText('header_html')->nullable();
            $table->longText('footer_html')->nullable();
            // Manual variable values filled at generation: { "key": "value" }
            $table->json('data')->nullable();

            $table->string('status')->default('brouillon'); // ContractStatus enum
            $table->unsignedInteger('version')->default(1);
            $table->string('pdf_path')->nullable();
            $table->string('signed_scan_path')->nullable();

            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Note: uuidMorphs('subject') already creates the (subject_type, subject_id) index.
            $table->index(['target', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contracts');
    }
};
