<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract remembers which letterhead it was printed with, so it can be
     * shown and changed when editing, and the font it is printed in.
     */
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->foreignUuid('letterhead_id')->nullable()->after('footer_html')
                ->constrained('letterheads')->nullOnDelete();
            $table->string('font', 32)->nullable()->after('letterhead_scope');
        });

        // Existing contracts: find their letterhead from the image path frozen in their header/footer.
        foreach (DB::table('letterheads')->get(['id', 'header_image_path', 'footer_image_path']) as $letterhead) {
            foreach (['header_image_path' => 'header_html', 'footer_image_path' => 'footer_html'] as $image => $column) {
                if (! $letterhead->{$image}) {
                    continue;
                }

                DB::table('contracts')
                    ->whereNull('letterhead_id')
                    ->where($column, 'like', '%'.basename($letterhead->{$image}).'%')
                    ->update(['letterhead_id' => $letterhead->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('letterhead_id');
            $table->dropColumn('font');
        });
    }
};
