<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_posts', function (Blueprint $table) {
            $table->id();
            $table->string('video_id', 16)->unique();
            $table->string('kind', 10)->index();          // clip | guest
            $table->string('title');
            $table->string('thumbnail_url')->nullable();
            $table->string('source_channel_id', 32);      // the (unregistered) channel the video is on
            $table->string('source_channel_name');
            $table->dateTime('published_at')->index();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('submitter_hash', 64)->nullable(); // hashed IP for abuse follow-up, never the IP itself
            $table->timestamps();
        });

        Schema::create('channel_video_post', function (Blueprint $table) {
            $table->foreignId('video_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->primary(['video_post_id', 'channel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_video_post');
        Schema::dropIfExists('video_posts');
    }
};
