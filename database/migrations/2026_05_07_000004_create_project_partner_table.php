<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_partner', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('partner_id')->constrained()->onDelete('cascade');
            $table->primary(['project_id', 'partner_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_partner');
    }
};
