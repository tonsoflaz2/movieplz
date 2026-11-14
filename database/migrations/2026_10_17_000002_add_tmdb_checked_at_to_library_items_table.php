<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When TMDB was last asked about a title, match or not, so titles TMDB
     * can't find stop being retried ahead of new ones. Titles enriched
     * before this column existed count as checked.
     */
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->timestamp('tmdb_checked_at')->nullable()->after('overview');
        });

        DB::table('library_items')
            ->where(fn ($q) => $q->whereNotNull('tmdb_id')->orWhereNotNull('poster_url'))
            ->update(['tmdb_checked_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('tmdb_checked_at');
        });
    }
};
