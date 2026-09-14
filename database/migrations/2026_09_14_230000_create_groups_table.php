<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->string('slug', 50)->unique();
            $table->timestamps();
        });

        Schema::create('channel_group', function (Blueprint $table) {
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->primary(['channel_id', 'group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_group');
        Schema::dropIfExists('groups');
    }
};
