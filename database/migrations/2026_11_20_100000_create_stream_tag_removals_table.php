<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tags an admin took off a stream. The dictionary would otherwise attach an
     * automatic tag again on the next fetch, so the tagger skips these pairs.
     */
    public function up(): void
    {
        Schema::create('stream_tag_removals', function (Blueprint $table) {
            $table->foreignId('stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['stream_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_tag_removals');
    }
};
