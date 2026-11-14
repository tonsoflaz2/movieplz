<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('push_token', 255)->nullable()->after('device_token');
            $table->string('push_platform', 10)->nullable()->default('ios')->after('push_token');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['push_token', 'push_platform']);
        });
    }
};
