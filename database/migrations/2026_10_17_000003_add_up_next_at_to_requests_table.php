<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the host said "I'll watch it": the suggestion is still pending,
     * but on their Up next list until they mark it seen.
     */
    public function up(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->timestamp('up_next_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('requests', function (Blueprint $table) {
            $table->dropColumn('up_next_at');
        });
    }
};
