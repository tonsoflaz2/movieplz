<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The color theme each person picked (App\Support\Theme). Null is
     * Classic, so everyone keeps today's look until they choose.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('theme', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
