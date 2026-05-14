<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('age_ranges', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('age_min');
            $table->integer('age_max');
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('age_ranges');
    }
};
