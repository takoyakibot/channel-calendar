<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            // When the X announce bot posted this stream; the tweet id is only set
            // for real posts, so limit counting can ignore the backfill below.
            $table->dateTime('announced_at')->nullable()->after('is_members_only');
            $table->string('announced_tweet_id', 32)->nullable()->after('announced_at');
            $table->index('announced_at');
        });

        // Everything already known counts as announced, so the first run of the
        // bot does not tweet the whole backlog.
        DB::table('streams')->whereNull('announced_at')->update(['announced_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropIndex(['announced_at']);
            $table->dropColumn(['announced_at', 'announced_tweet_id']);
        });
    }
};
