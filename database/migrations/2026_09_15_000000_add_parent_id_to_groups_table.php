<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('groups_slug_unique');
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('groups')->cascadeOnDelete();
            $table->unique(['parent_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'slug']);
            $table->dropConstrainedForeignId('parent_id');
            $table->unique('slug');
        });
    }
};
