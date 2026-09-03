<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('started_at');
            $table->date('ended_at');
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'position']);
        });

        Schema::create('phase_user', function (Blueprint $table) {
            $table->foreignUuid('phase_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['phase_id', 'user_id']);
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('phase_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('started_at');
            $table->date('ended_at');
            $table->json('resources')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['phase_id', 'position']);
        });

        Schema::create('activity_user', function (Blueprint $table) {
            $table->foreignUuid('activity_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['activity_id', 'user_id']);
        });

        Schema::create('tasks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('activity_id')->constrained()->onDelete('restrict');
            $table->foreignUuid('created_by')->constrained('users')->onDelete('restrict');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('started_at');
            $table->date('ended_at');
            $table->string('status')->default('todo');
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['activity_id', 'position']);
            $table->index('status');
        });

        Schema::create('task_user', function (Blueprint $table) {
            $table->foreignUuid('task_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->primary(['task_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_user');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('activity_user');
        Schema::dropIfExists('activities');
        Schema::dropIfExists('phase_user');
        Schema::dropIfExists('phases');
    }
};
