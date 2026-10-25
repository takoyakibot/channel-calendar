<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            // A member's TikTok account, for linking TikTok LIVE entries to their live page.
            $table->string('tiktok_handle', 24)->nullable()->after('twitch_user_id');
        });

        Schema::table('manual_schedules', function (Blueprint $table) {
            // Where the stream happens when it is not something we fetch: 'tiktok'.
            // Null is the usual entry, replaced once the real YouTube / Twitch stream appears.
            $table->string('platform', 10)->nullable()->after('is_all_day');
        });
    }

    public function down(): void
    {
        Schema::table('manual_schedules', function (Blueprint $table) {
            $table->dropColumn('platform');
        });
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn('tiktok_handle');
        });
    }
};
