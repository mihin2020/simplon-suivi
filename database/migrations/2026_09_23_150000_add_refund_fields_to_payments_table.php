<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('en_attente','paye','en_retard','annule','rembourse') NOT NULL DEFAULT 'en_attente'");
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->timestamp('refunded_at')->nullable()->after('paid_at');
            $table->unsignedBigInteger('refund_amount')->nullable()->after('refunded_at')->comment('Montant remboursé en FCFA');
            $table->string('refund_motif', 500)->nullable()->after('refund_amount');
            $table->foreignUuid('refunded_by')->nullable()->after('refund_motif')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn(['refunded_at', 'refund_amount', 'refund_motif']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("UPDATE payments SET status = 'annule' WHERE status = 'rembourse'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN status ENUM('en_attente','paye','en_retard','annule') NOT NULL DEFAULT 'en_attente'");
        }
    }
};
