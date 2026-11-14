<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Disconnecting a phone without deleting anything, and coming back.
     *
     * reconnect_code: the person's own reconnect code, encrypted (they can
     * see it again while connected). reconnect_code_hash: an HMAC of it, for
     * the lookup when someone types it in. disconnected_at: set when they
     * disconnect; no pushes go to them until they reconnect.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('reconnect_code')->nullable()->after('push_environment');
            $table->string('reconnect_code_hash', 64)->nullable()->unique()->after('reconnect_code');
            $table->timestamp('disconnected_at')->nullable()->after('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropUnique(['reconnect_code_hash']);
            $table->dropColumn(['reconnect_code', 'reconnect_code_hash', 'disconnected_at']);
        });
    }
};
