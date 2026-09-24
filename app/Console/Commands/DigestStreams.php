<?php

namespace App\Console\Commands;

use App\Models\Setting;
use App\Models\Stream;
use App\Services\XPoster;
use App\Services\XPosterException;
use App\Support\StreamDigest;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

class DigestStreams extends Command
{
    /** JST date (Y-m-d) of the last digest that was actually posted. */
    public const POSTED_ON_KEY = 'x_digest_posted_on';

    protected $signature = 'streams:digest
        {--dry-run : Print the digest without posting or marking anything}
        {--force : Post even if today\'s digest was already posted}';

    protected $description = 'Post one X digest of streams live now and reserved for later (once per JST day)';

    public function handle(XPoster $poster): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = now('Asia/Tokyo')->toDateString();

        if (! $dryRun && ! $poster->isConfigured()) {
            $this->info('X credentials are not configured; no digest posted.');

            return self::SUCCESS;
        }

        if (! $dryRun && ! $this->option('force') && Setting::get(self::POSTED_ON_KEY) === $today) {
            $this->info("Today's digest ({$today}) was already posted.");

            return self::SUCCESS;
        }

        $live = $this->publicStreams()->where('status', 'live')->orderBy('scheduled_at')->get();
        $upcoming = $this->publicStreams()->where('status', 'upcoming')->where('scheduled_at', '>', now())->orderBy('scheduled_at')->get();

        $text = StreamDigest::text($live, $upcoming, now());
        if ($text === null) {
            $this->info('Nothing live or reserved; no digest today.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->line('--- would post:');
            $this->line($text);

            return self::SUCCESS;
        }

        try {
            $tweetId = $poster->post($text);
        } catch (XPosterException $e) {
            $this->error("Failed to post the digest: {$e->getMessage()}");
            Log::warning("streams:digest failed (HTTP {$e->getCode()}): {$e->getMessage()}");
            if ($e->isOutOfCredits()) {
                $this->warn('X credits depleted — top up in the X Developer Console to resume the daily digest.');
            }

            return self::SUCCESS;
        }

        Setting::set(self::POSTED_ON_KEY, $today);
        $this->info("Posted today's digest (tweet {$tweetId}).");

        return self::SUCCESS;
    }

    /** Streams of active channels that anyone can watch. */
    private function publicStreams(): Builder
    {
        return Stream::with('channel')
            ->whereHas('channel', fn (Builder $q) => $q->where('is_active', true))
            ->where('is_members_only', false);
    }
}
