<?php

namespace App\Support;

use App\Models\Setting;

class YouTubeApiKey
{
    public const SETTING_KEY = 'youtube_api_key';

    public function resolve(): ?string
    {
        return Setting::get(self::SETTING_KEY) ?: (config('services.youtube.api_key') ?: null);
    }

    /** Where the active key comes from: "database", "env" or "none". */
    public function source(): string
    {
        if (Setting::get(self::SETTING_KEY)) {
            return 'database';
        }

        return config('services.youtube.api_key') ? 'env' : 'none';
    }

    /** Last few characters of the active key for display, e.g. "••••9876". */
    public function masked(): ?string
    {
        $key = $this->resolve();

        return $key ? '••••' . substr($key, -4) : null;
    }
}
