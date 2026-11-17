<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The library list's order (a host's titles by sort title, then id),
     * as an index, so each page is read in order instead of sorted.
     */
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->index(['host_id', 'sort_title', 'id'], 'library_items_host_sort_index');
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropIndex('library_items_host_sort_index');
        });
    }
};
