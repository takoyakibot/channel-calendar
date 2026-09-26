<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Games and categories streams are tagged with (issue #50).
        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('kind', 10)->index();   // game | category
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Normalised spellings that map to a tag ("マイクラ" → Minecraft); the tag's
        // own name is stored here too so matching only ever consults this table.
        Schema::create('tag_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('alias', 80)->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Bracket terms that are noise ("新人Vtuber") and must not be suggested again.
        Schema::create('ignored_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 80)->unique();
            $table->string('display', 80);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Bracket terms nobody has classified yet, rebuilt from all titles (issue #51).
        Schema::create('unmatched_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term', 80)->unique();
            $table->string('display', 80);
            $table->unsignedInteger('count')->default(0);
            $table->dateTime('last_seen_at');
            $table->timestamps();
        });

        Schema::create('stream_tag', function (Blueprint $table) {
            $table->foreignId('stream_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->string('source', 8)->default('auto');   // auto (dictionary) | manual (someone added it)
            $table->primary(['stream_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stream_tag');
        Schema::dropIfExists('unmatched_terms');
        Schema::dropIfExists('ignored_terms');
        Schema::dropIfExists('tag_aliases');
        Schema::dropIfExists('tags');
    }
};
