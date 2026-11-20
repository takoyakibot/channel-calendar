<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // When the row was last confirmed against the YouTube API. YouTube's
        // developer policies only allow keeping API data that is refreshed
        // (or deleted) within 30 days, so streams:refresh walks the oldest first.
        Schema::table('streams', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->index();
        });

        // streams:backfill progress per channel: the next playlist page of the
        // uploads / members-only lists, and when both were read to the end.
        Schema::table('channels', function (Blueprint $table) {
            $table->json('backfill_state')->nullable();
            $table->timestamp('backfill_completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropIndex(['verified_at']);
            $table->dropColumn('verified_at');
        });
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['backfill_state', 'backfill_completed_at']);
        });
    }
};
