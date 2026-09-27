<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Twitch Helix with an app access token (client credentials flow): public data
 * only — who is live, their schedule, and past broadcasts.
 */
class TwitchService
{
    private const TOKEN_CACHE_KEY = 'twitch.app_token';

    public function __construct(private ?string $clientId, private ?string $clientSecret)
    {
    }

    public function isConfigured(): bool
    {
        return ! empty($this->clientId) && ! empty($this->clientSecret);
    }

    /** @return ?array{id: string, login: string, display_name: string, profile_image_url: ?string} */
    public function getUserByLogin(string $login): ?array
    {
        $user = $this->get('users', ['login' => $login])['data'][0] ?? null;

        return $user ? [
            'id' => (string) $user['id'],
            'login' => $user['login'],
            'display_name' => $user['display_name'],
            'profile_image_url' => $user['profile_image_url'] ?? null,
        ] : null;
    }

    /** The broadcast running right now, or null. */
    public function getLiveStream(string $userId): ?array
    {
        $s = $this->get('streams', ['user_id' => $userId, 'first' => 1])['data'][0] ?? null;

        return $s ? [
            'id' => (string) $s['id'],
            'title' => (string) ($s['title'] ?? ''),
            'started_at' => $s['started_at'],
            'game_name' => $s['game_name'] ?? null,
            'thumbnail_url' => self::thumbnail($s['thumbnail_url'] ?? null),
        ] : null;
    }

    /**
     * Upcoming schedule segments (Twitch returns 404 when the streamer has no
     * schedule, which simply means none). Cancelled segments are dropped.
     *
     * @return array<int, array{id: string, title: string, start_time: string, end_time: ?string, category: ?string}>
     */
    public function getSchedule(string $userId): array
    {
        $segments = $this->get('schedule', ['broadcaster_id' => $userId, 'first' => 25])['data']['segments'] ?? [];

        $out = [];
        foreach ($segments as $seg) {
            if (! empty($seg['canceled_until'])) {
                continue;
            }
            $out[] = [
                'id' => (string) $seg['id'],
                'title' => (string) ($seg['title'] ?? ''),
                'start_time' => $seg['start_time'],
                'end_time' => $seg['end_time'] ?? null,
                'category' => $seg['category']['name'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Recent past broadcasts (VODs), newest first. stream_id ties a VOD back to
     * the live stream it was recorded from.
     *
     * @return array<int, array{id: string, stream_id: ?string, title: string, created_at: string, duration_seconds: int, url: string, thumbnail_url: ?string}>
     */
    public function getArchives(string $userId, int $first = 20): array
    {
        $videos = $this->get('videos', ['user_id' => $userId, 'type' => 'archive', 'first' => $first])['data'] ?? [];

        return array_map(fn (array $v) => [
            'id' => (string) $v['id'],
            'stream_id' => isset($v['stream_id']) ? (string) $v['stream_id'] : null,
            'title' => (string) ($v['title'] ?? ''),
            'created_at' => $v['created_at'],
            'duration_seconds' => self::durationToSeconds((string) ($v['duration'] ?? '')),
            'url' => $v['url'],
            'thumbnail_url' => self::thumbnail($v['thumbnail_url'] ?? null),
        ], $videos);
    }

    /** Twitch durations look like "3h2m1s". */
    public static function durationToSeconds(string $duration): int
    {
        $seconds = 0;
        if (preg_match_all('/(\d+)([hms])/', $duration, $m, PREG_SET_ORDER)) {
            foreach ($m as [, $n, $unit]) {
                $seconds += (int) $n * ['h' => 3600, 'm' => 60, 's' => 1][$unit];
            }
        }

        return $seconds;
    }

    /** Thumbnail templates carry {width}x{height} (streams) or %{width}x%{height} (videos). */
    private static function thumbnail(?string $template): ?string
    {
        return $template ? str_replace(['%{width}', '%{height}', '{width}', '{height}'], ['320', '180', '320', '180'], $template) : null;
    }

    private function get(string $path, array $query): array
    {
        $response = $this->request($path, $query);
        if ($response->status() === 401) {
            // Token revoked or expired early: fetch a fresh one and retry once.
            Cache::forget(self::TOKEN_CACHE_KEY);
            $response = $this->request($path, $query);
        }
        if ($response->status() === 404) {
            return ['data' => []];
        }

        return $response->throw()->json() ?? ['data' => []];
    }

    private function request(string $path, array $query): Response
    {
        return Http::timeout(10)
            ->withToken($this->token())
            ->withHeaders(['Client-Id' => (string) $this->clientId])
            ->get("https://api.twitch.tv/helix/{$path}", $query);
    }

    /** @throws RequestException */
    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE_KEY, now()->addDay(), function () {
            $json = Http::timeout(10)->asForm()->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'client_credentials',
            ])->throw()->json();

            return (string) $json['access_token'];
        });
    }
}
