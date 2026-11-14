<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which APNs gateway an iOS token belongs to: 'production' (App Store,
     * TestFlight) or 'development' (builds signed for development). Null
     * until the device says or a push finds out.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('push_environment', 12)->nullable()->after('push_platform');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('push_environment');
        });
    }
};
