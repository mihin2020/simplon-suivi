<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('contact_first_name')->nullable()->after('logo_path');
            $table->string('contact_last_name')->nullable()->after('contact_first_name');
            $table->string('contact_email')->nullable()->after('contact_last_name');
            $table->string('contact_phone')->nullable()->after('contact_email');
            $table->string('contact_profile')->nullable()->after('contact_phone');
            $table->string('contact_position')->nullable()->after('contact_profile');
        });
    }

    public function down(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->dropColumn([
                'contact_first_name',
                'contact_last_name',
                'contact_email',
                'contact_phone',
                'contact_profile',
                'contact_position',
            ]);
        });
    }
};
