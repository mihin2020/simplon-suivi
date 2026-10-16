<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where to send the contract. Takes precedence over the recipient record's
     * address, and is the only address of a contract with no recipient record
     * (written without one, or generated from an Excel model).
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->after('subject_id');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('recipient_email');
        });
    }
};
