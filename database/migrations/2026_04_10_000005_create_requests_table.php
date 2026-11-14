<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();

            $table->string('title');
            $table->unsignedSmallInteger('year')->nullable();
            $table->enum('type', ['film', 'series'])->nullable();
            $table->text('notes')->nullable();

            $table->enum('status', ['pending', 'fulfilled', 'declined'])->default('pending');
            $table->timestamp('fulfilled_at')->nullable();
            $table->foreignId('fulfilled_library_item_id')
                ->nullable()
                ->constrained('library_items')
                ->nullOnDelete();

            // Used to suppress repeat "your request was added!" notifications
            // once the client has visited their history view after fulfillment.
            $table->timestamp('seen_by_client_at')->nullable();

            $table->timestamps();

            $table->index(['host_id', 'status']);
            $table->index(['client_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
