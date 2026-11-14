<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained()->cascadeOnDelete();
            $table->string('title');

            // Articles moved to end, for alpha sort. e.g. "The Matrix" -> "Matrix, The"
            $table->string('sort_title')->index();

            $table->enum('type', ['film', 'series']);
            $table->unsignedSmallInteger('year')->nullable();

            // Stretch fields — nullable in v1, used if TMDB enrichment is added.
            $table->string('tmdb_id')->nullable();
            $table->string('poster_url')->nullable();

            // When the host added this to Plex (not when the row was inserted).
            $table->timestamp('added_at')->useCurrent();

            $table->timestamps();

            $table->unique(['host_id', 'title', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_items');
    }
};
