<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('target'); // ContractTarget enum
            $table->longText('body_html')->nullable();
            $table->longText('header_html')->nullable(); // overrides document_settings when set
            $table->longText('footer_html')->nullable();
            // Manual variables defined on the template: [{ "key": "...", "label": "..." }]
            $table->json('manual_variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('target');
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_templates');
    }
};
