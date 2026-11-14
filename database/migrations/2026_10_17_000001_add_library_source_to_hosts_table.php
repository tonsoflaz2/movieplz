<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Each host syncs its own library from its own public Google Sheet.
     * Until now every host was synced from the owner's sheet; only the
     * first host (the owner's) keeps it.
     */
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->string('library_sheet_url', 500)->nullable()->after('name');
            $table->timestamp('library_synced_at')->nullable()->after('library_sheet_url');
        });

        $owner = DB::table('hosts')->min('id');
        if ($owner) {
            DB::table('hosts')->where('id', $owner)->update([
                'library_sheet_url' => 'https://docs.google.com/spreadsheets/d/1Amo2whUKbBBLi3aW2vgcEtQ1vTj1N2_eU_fkIUNog-A/edit#gid=0',
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn(['library_sheet_url', 'library_synced_at']);
        });
    }
};
