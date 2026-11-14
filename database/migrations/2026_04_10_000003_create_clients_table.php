<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invite_id')->nullable()->constrained()->nullOnDelete();

            // The host is just a clients row with role='host'. Same auth
            // flow, same session mechanism — only the role differs.
            $table->enum('role', ['client', 'host'])->default('client');

            $table->string('display_name');

            // Audit-only random string; not used for auth (sessions are).
            $table->string('device_token', 64)->nullable();

            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['host_id', 'role']);
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
