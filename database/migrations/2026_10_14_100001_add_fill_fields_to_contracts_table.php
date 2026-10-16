<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The person-specific passages of a contract (name, amount, dates…),
     * detected in the background. They become the columns of the Excel model
     * used to generate the same contract for many people.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->json('fill_fields')->nullable()->after('data');
            $table->string('fill_fields_status', 20)->nullable()->after('fill_fields');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn(['fill_fields', 'fill_fields_status']);
        });
    }
};
