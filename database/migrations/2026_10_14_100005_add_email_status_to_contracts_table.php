<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where the last email sending of a contract stands: queued, sent or failed
     * (null = never sent). Filtered on in the list, hence the index.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('email_status', 20)->nullable()->after('email_count')->index();
        });

        // Contracts already emailed before this column existed.
        DB::table('contracts')->whereNotNull('emailed_at')->update(['email_status' => 'sent']);
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropIndex(['email_status']);
            $table->dropColumn('email_status');
        });
    }
};
