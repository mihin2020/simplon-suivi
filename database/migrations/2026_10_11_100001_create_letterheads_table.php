<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named pair of uploaded header/footer images, printed at the A4 width.
     * Either side may be missing.
     */
    public function up(): void
    {
        Schema::create('letterheads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('header_image_path')->nullable();
            $table->decimal('header_height_mm', 5, 1)->nullable();
            $table->string('footer_image_path')->nullable();
            $table->decimal('footer_height_mm', 5, 1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('letterheads');
    }
};
