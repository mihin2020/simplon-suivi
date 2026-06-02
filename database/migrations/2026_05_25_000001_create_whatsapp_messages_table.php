<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('phone');
            $table->string('recipient_name')->nullable();
            $table->text('message');
            $table->string('provider')->default('twilio'); // twilio | meta
            $table->string('status')->default('sent');     // sent | failed
            $table->string('external_id')->nullable();     // SID Twilio ou message_id Meta
            $table->text('error')->nullable();
            $table->string('formation_name')->nullable();
            $table->string('project_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
