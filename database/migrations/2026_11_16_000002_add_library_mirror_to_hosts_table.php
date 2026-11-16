<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Mirror mode: the host's Google Sheet is their whole library, so a title
     * the sheet drops leaves the library on the next sync (titles added in the
     * app or pasted stay). Off for every existing host, so nothing changes
     * until a host turns it on.
     *
     * library_mirror_held: how many titles the last sync held back because
     * the sheet suddenly looked much smaller (null when nothing is held).
     */
    public function up(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->boolean('library_mirror')->default(false)->after('library_synced_at');
            $table->unsignedInteger('library_mirror_held')->nullable()->after('library_mirror');
        });
    }

    public function down(): void
    {
        Schema::table('hosts', function (Blueprint $table) {
            $table->dropColumn(['library_mirror', 'library_mirror_held']);
        });
    }
};
