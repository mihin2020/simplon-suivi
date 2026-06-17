<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->string('direction')->default('sent')->after('message'); // sent | received
            $table->uuid('learner_id')->nullable()->after('direction');
            $table->foreign('learner_id')->references('id')->on('learners')->nullOnDelete();
            // Change default provider to wwebjs
            $table->string('provider')->default('wwebjs')->change();
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropForeign(['learner_id']);
            $table->dropColumn(['direction', 'learner_id']);
            $table->string('provider')->default('twilio')->change();
        });
    }
};
