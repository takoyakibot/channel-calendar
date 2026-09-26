<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            // Length of shorts/uploads (and ended broadcasts) from contentDetails, so the
            // calendar can show their real end instead of a guessed duration.
            $table->unsignedInteger('duration_seconds')->nullable()->after('type');
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn('duration_seconds');
        });
    }
};
