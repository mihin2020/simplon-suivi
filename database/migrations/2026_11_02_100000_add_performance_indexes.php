<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Shared by every page: the unread-mailbox badge counts distinct threads by direction/archive/read state.
        Schema::table('emails', function (Blueprint $table) {
            $table->index(['direction', 'is_archived', 'is_read', 'thread_id'], 'emails_inbox_state_index');
        });

        // Shared by every page: the unread-notifications badge.
        Schema::table('notifications', function (Blueprint $table) {
            $table->index(['user_id', 'read_at'], 'notifications_user_unread_index');
        });

        // Dashboards, statistics and project pages count learners per formation and per status.
        Schema::table('formation_learner', function (Blueprint $table) {
            $table->index(['formation_id', 'status'], 'formation_learner_formation_status_index');
            $table->index(['learner_id', 'status'], 'formation_learner_learner_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropIndex('emails_inbox_state_index');
        });

        Schema::table('notifications', function (Blueprint $table) {
            $table->dropIndex('notifications_user_unread_index');
        });

        Schema::table('formation_learner', function (Blueprint $table) {
            $table->dropIndex('formation_learner_formation_status_index');
            $table->dropIndex('formation_learner_learner_status_index');
        });
    }
};
