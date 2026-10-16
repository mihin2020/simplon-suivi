<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract project groups the contracts written for one purpose
     * (e.g. the trainers of a cohort): one base contract, then one copy per person.
     */
    public function up(): void
    {
        Schema::create('contract_projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('target', 30)->index();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignUuid('contract_project_id')->nullable()->after('contract_template_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contract_project_id');
        });

        Schema::dropIfExists('contract_projects');
    }
};
