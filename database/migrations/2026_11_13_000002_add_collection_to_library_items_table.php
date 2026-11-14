<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where each library title came from, whether it's in the host's
     * collection, and the sheet's own spelling of it.
     *
     * origin: sheet (Google Sheet sync), paste (pasted titles), app ("Saw
     * it!" on a friend's suggestion of a title the library didn't have).
     * in_collection: the host's permanent collection. Sheet and paste
     * titles are; app titles wait in TBW until the host keeps them (or the
     * sheet brings the same title). source_title / source_year: the title as
     * the sheet or paste gave it. Sync matches on them, so a title the host
     * renames in the app is neither duplicated nor undone by the next sync.
     * edited_at: when the host last edited it.
     */
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->string('origin', 8)->default('sheet')->after('type');
            $table->boolean('in_collection')->default(true)->after('origin');
            $table->string('source_title')->nullable()->after('in_collection');
            $table->unsignedSmallInteger('source_year')->nullable()->after('source_title');
            $table->timestamp('edited_at')->nullable()->after('tmdb_checked_at');
            $table->index(['host_id', 'source_title']);
        });

        // Every existing title keeps its sheet/paste spelling as its key.
        DB::table('library_items')->update([
            'source_title' => DB::raw('title'),
            'source_year' => DB::raw('year'),
        ]);

        // Titles made by "Saw it!": a fulfilled suggestion points at them and
        // they appeared within a minute of it. They wait in TBW, unkept.
        $fromApp = DB::table('requests')
            ->join('library_items', 'library_items.id', '=', 'requests.fulfilled_library_item_id')
            ->whereNotNull('requests.fulfilled_at')
            ->get(['library_items.id', 'library_items.created_at', 'requests.fulfilled_at'])
            ->filter(fn ($r) => abs(strtotime((string) $r->created_at) - strtotime((string) $r->fulfilled_at)) <= 60)
            ->pluck('id')
            ->unique()
            ->all();
        foreach (array_chunk($fromApp, 500) as $ids) {
            DB::table('library_items')->whereIn('id', $ids)->update([
                'origin' => 'app',
                'in_collection' => false,
                'source_title' => null,
                'source_year' => null,
            ]);
        }

        // The rest came from a sheet if the host has one linked, else a paste.
        DB::table('library_items')
            ->where('origin', 'sheet')
            ->whereIn('host_id', DB::table('hosts')->whereNull('library_sheet_url')->select('id'))
            ->update(['origin' => 'paste']);
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropIndex(['host_id', 'source_title']);
            $table->dropColumn(['origin', 'in_collection', 'source_title', 'source_year', 'edited_at']);
        });
    }
};
