<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Signature block printed at the end of the contract, on two columns:
     * {left_title, left_name, right_title, right_name}. Kept out of the body
     * because the editor cannot lay out columns.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('signatories')->nullable()->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('signatories');
        });
    }
};
