<?php

namespace App\Console\Commands;

use App\Support\StreamTagger;
use Illuminate\Console\Command;

class RetagStreams extends Command
{
    protected $signature = 'streams:retag';

    protected $description = 'Re-apply the tag dictionary to every stream and rebuild the unmatched-term list';

    public function handle(StreamTagger $tagger): int
    {
        $n = $tagger->retagAll();
        $this->info("Re-tagged {$n} stream(s).");

        return self::SUCCESS;
    }
}
