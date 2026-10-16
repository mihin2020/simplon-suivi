<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Last time a contract was emailed, to whom, and how many times: shown in
     * the lists and the sending dialog so a contract is not sent twice by mistake.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->timestamp('emailed_at')->nullable()->after('recipient_email');
            $table->string('emailed_to', 1000)->nullable()->after('emailed_at');
            $table->unsignedInteger('email_count')->default(0)->after('emailed_to');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['emailed_at', 'emailed_to', 'email_count']);
        });
    }
};
