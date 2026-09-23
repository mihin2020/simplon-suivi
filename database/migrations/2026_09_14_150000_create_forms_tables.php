<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('formation_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('public_token', 64)->unique();
            $table->json('settings')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
            $table->index('formation_id');
            $table->index('status');
        });

        Schema::create('form_fields', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('type', 40);
            $table->string('label');
            $table->text('help_text')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->string('learner_attribute', 64)->nullable();
            $table->json('options')->nullable();
            $table->json('validation')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['form_id', 'position']);
            $table->index('learner_attribute');
        });

        Schema::create('form_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('status', 20)->default('submitted');
            $table->string('email')->nullable();
            $table->foreignUuid('learner_id')->nullable()->constrained('learners')->nullOnDelete();
            $table->timestamp('submitted_at');
            $table->timestamp('reviewed_at')->nullable();
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['form_id', 'status']);
            $table->index(['form_id', 'email']);
            $table->index('submitted_at');
        });

        Schema::create('form_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('form_response_id')->constrained('form_responses')->cascadeOnDelete();
            $table->foreignUuid('form_field_id')->constrained('form_fields')->cascadeOnDelete();
            $table->text('value_text')->nullable();
            $table->json('value_json')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_original_name')->nullable();
            $table->timestamps();

            $table->unique(['form_response_id', 'form_field_id']);
            $table->index('form_field_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_answers');
        Schema::dropIfExists('form_responses');
        Schema::dropIfExists('form_fields');
        Schema::dropIfExists('forms');
    }
};
