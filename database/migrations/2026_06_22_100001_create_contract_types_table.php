<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('context');
            $table->unsignedInteger('order')->default(0);
            $table->timestamps();

            $table->unique(['name', 'context']);
            $table->index('context');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_types');
    }
};
