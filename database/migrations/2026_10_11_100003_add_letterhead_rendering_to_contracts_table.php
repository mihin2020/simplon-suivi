<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Frozen letterhead geometry. Null heights mean the contract still uses
     * the legacy free-form HTML header/footer.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->decimal('header_height_mm', 5, 1)->nullable()->after('footer_html');
            $table->decimal('footer_height_mm', 5, 1)->nullable()->after('header_height_mm');
            $table->string('letterhead_scope', 20)->nullable()->after('footer_height_mm');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['header_height_mm', 'footer_height_mm', 'letterhead_scope']);
        });
    }
};
