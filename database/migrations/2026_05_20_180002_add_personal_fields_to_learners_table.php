<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->string('cnib_number')->nullable()->unique()->after('cnib_path');
            $table->enum('marital_status', ['single', 'married', 'divorced', 'widowed'])->nullable()->after('cnib_number');
            $table->unsignedTinyInteger('children_count')->default(0)->after('marital_status');
            $table->foreignUuid('vulnerability_id')->nullable()->constrained('vulnerabilities')->nullOnDelete()->after('children_count');
            $table->foreignUuid('last_diploma_id')->nullable()->constrained('last_diplomas')->nullOnDelete()->after('vulnerability_id');
            $table->string('cv_path')->nullable()->after('last_diploma_id');
            $table->string('cv_original_name')->nullable()->after('cv_path');
        });
    }

    public function down(): void
    {
        Schema::table('learners', function (Blueprint $table) {
            $table->dropForeign(['vulnerability_id']);
            $table->dropForeign(['last_diploma_id']);
            $table->dropColumn([
                'cnib_number',
                'marital_status',
                'children_count',
                'vulnerability_id',
                'last_diploma_id',
                'cv_path',
                'cv_original_name',
            ]);
        });
    }
};
