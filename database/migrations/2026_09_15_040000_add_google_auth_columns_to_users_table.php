<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('google_id')->nullable()->unique()->after('id');
            $table->string('avatar_url', 2048)->nullable()->after('email');
            $table->boolean('is_admin')->default(false)->after('remember_token');
            $table->boolean('is_banned')->default(false)->after('is_admin');
        });

        // Allow null password for OAuth-only users.
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar_url', 'is_admin', 'is_banned']);
            $table->string('password')->nullable(false)->change();
        });
    }
};
