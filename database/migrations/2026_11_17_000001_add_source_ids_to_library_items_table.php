<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IDs a host's Google Sheet gives for a title (TMDB, IMDb, TVDB
     * columns), so its poster and details come from that exact entry rather
     * than a search by name. matched_by says how the title's TMDB entry was
     * found: tmdb_id, imdb_id or tvdb_id (the sheet's IDs), host (picked by
     * hand with Edit), or null (search by name, or copied from another
     * library). All nullable, so existing rows are left as they are.
     */
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('source_tmdb_id', 20)->nullable()->after('source_year');
            $table->string('source_imdb_id', 20)->nullable()->after('source_tmdb_id');
            $table->string('source_tvdb_id', 20)->nullable()->after('source_imdb_id');
            $table->string('matched_by', 10)->nullable()->after('tmdb_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn(['source_tmdb_id', 'source_imdb_id', 'source_tvdb_id', 'matched_by']);
        });
    }
};
