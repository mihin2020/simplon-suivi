<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insertion_records', function (Blueprint $table) {
            $table->string('internship_contract_path')->nullable()->after('internship_work_mode');
            $table->string('internship_contract_original_name')->nullable()->after('internship_contract_path');
            $table->string('employment_contract_path')->nullable()->after('employment_work_mode');
            $table->string('employment_contract_original_name')->nullable()->after('employment_contract_path');
        });
    }

    public function down(): void
    {
        Schema::table('insertion_records', function (Blueprint $table) {
            $table->dropColumn([
                'internship_contract_path',
                'internship_contract_original_name',
                'employment_contract_path',
                'employment_contract_original_name',
            ]);
        });
    }
};
