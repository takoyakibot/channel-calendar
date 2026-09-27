<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // A member's Twitch account, so their Twitch streams share the channel's colour and column.
            $table->string('twitch_login', 25)->nullable()->after('x_search_keywords');
            $table->string('twitch_user_id', 32)->nullable()->after('twitch_login');
        });

        Schema::table('streams', function (Blueprint $table) {
            $table->string('platform', 10)->default('youtube')->after('type')->index();
            // Where the entry links to when it is not a YouTube video (Twitch VOD / channel page).
            $table->string('url', 255)->nullable()->after('platform');
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn(['platform', 'url']);
        });
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['twitch_login', 'twitch_user_id']);
        });
    }
};
