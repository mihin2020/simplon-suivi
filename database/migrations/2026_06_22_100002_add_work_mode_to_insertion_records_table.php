<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insertion_records', function (Blueprint $table) {
            $table->string('internship_work_mode')->nullable()->after('internship_contract_type');
            $table->string('employment_work_mode')->nullable()->after('employment_contract_type');
        });
    }

    public function down(): void
    {
        Schema::table('insertion_records', function (Blueprint $table) {
            $table->dropColumn(['internship_work_mode', 'employment_work_mode']);
        });
    }
};
