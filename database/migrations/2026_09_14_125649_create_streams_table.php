<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('streams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('video_id', 255)->unique();
            $table->string('title', 1024);
            $table->text('description')->nullable();
            $table->string('thumbnail_url', 1024)->nullable();
            $table->dateTime('scheduled_at');
            $table->dateTime('actual_start_at')->nullable();
            $table->dateTime('actual_end_at')->nullable();
            $table->enum('status', ['upcoming', 'live', 'completed'])->default('upcoming');
            $table->timestamps();

            $table->index('scheduled_at');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('streams');
    }
};
