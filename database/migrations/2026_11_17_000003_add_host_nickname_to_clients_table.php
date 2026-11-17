<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the host calls a friend, shown only to the host (their
     * suggestions inbox, Clients, TBW, notifications). Null: the name the
     * friend picked. The friend never sees it.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('host_nickname', 40)->nullable()->after('display_name');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('host_nickname');
        });
    }
};
