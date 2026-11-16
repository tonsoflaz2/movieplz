<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the host wrote back when answering a suggestion (In progress,
     * Added or Done, Declined). The friend sees it with the suggestion and
     * in the notification. Adding a nullable column leaves existing rows as
     * they are.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->text('host_reply')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('host_reply');
        });
    }
};
