<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invites', function (Blueprint $table) {
            $table->id();

            // Nullable because the first-ever host-bootstrap invite is
            // created *before* the hosts row exists. Populated on
            // redemption of grants_host=true, or pre-set for in-app
            // client invites.
            $table->foreignId('host_id')->nullable()->constrained()->nullOnDelete();

            $table->string('code', 5)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('redeemed_at')->nullable();

            // No FK constraint — avoids a circular migration dependency
            // with clients. Integrity is enforced at the app layer.
            $table->unsignedBigInteger('redeemed_by_client_id')->nullable()->index();

            $table->boolean('grants_host')->default(false);
            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invites');
    }
};
