<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Civil-status fields needed on trainer contracts (date/place of birth,
     * address, ID number) so they fill automatically instead of being typed
     * again for every contract.
     */
    public function up(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->date('birth_date')->nullable()->after('phone2');
            $table->string('birth_place')->nullable()->after('birth_date');
            $table->string('address')->nullable()->after('birth_place');
            $table->string('id_number')->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('trainers', function (Blueprint $table) {
            $table->dropColumn(['birth_date', 'birth_place', 'address', 'id_number']);
        });
    }
};
