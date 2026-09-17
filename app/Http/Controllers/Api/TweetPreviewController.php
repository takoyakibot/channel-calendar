<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

/**
 * Resolves a tweet URL to its text and author through X's public oEmbed endpoint
 * (no API key required), so volunteers can pre-fill a manual schedule from an
 * announcement post. Fetched server-side to avoid CORS and to cache per tweet.
 */
class TweetPreviewController extends Controller
{
    private const TWEET_URL = '~^https?://(?:www\.|mobile\.)?(?:x\.com|twitter\.com)/([A-Za-z0-9_]{1,15})/status/(\d+)~i';

    public function show(Request $request): JsonResponse
    {
        $request->validate(['url' => 'required|string|max:2048']);

        if (! preg_match(self::TWEET_URL, trim($request->url), $m)) {
            throw ValidationException::withMessages(['url' => 'X（Twitter）の投稿 URL を指定してください。']);
        }

        $canonical = "https://twitter.com/{$m[1]}/status/{$m[2]}";

        $result = Cache::remember('tweet-preview:' . $m[2], now()->addDay(), function () use ($canonical, $m) {
            $response = Http::timeout(8)->acceptJson()->get('https://publish.twitter.com/oembed', [
                'url' => $canonical,
                'omit_script' => 1,
                'lang' => 'ja',
                'dnt' => 1,
            ]);

            if (! $response->successful()) {
                return ['error' => $response->status()];
            }

            $authorUrl = (string) $response->json('author_url');

            return [
                'author_name' => $response->json('author_name'),
                'author_url' => $authorUrl,
                // Handle from the canonical author URL (falls back to the one in the pasted URL).
                'author_handle' => preg_match('~(?:x\.com|twitter\.com)/([A-Za-z0-9_]{1,15})~i', $authorUrl, $am) ? $am[1] : $m[1],
                'text' => self::extractText((string) $response->json('html')),
            ];
        });

        if (isset($result['error'])) {
            Cache::forget('tweet-preview:' . $m[2]);
            $status = $result['error'] === 404 ? 404 : 502;

            return response()->json(['message' => $status === 404
                ? '投稿が見つかりません（削除済み・非公開の可能性があります）。'
                : 'X から投稿を取得できませんでした。'], $status);
        }

        return response()->json($result);
    }

    /** The oEmbed HTML wraps the post body in a single <p>; turn it back into plain text. */
    private static function extractText(string $html): string
    {
        $body = preg_match('~<p[^>]*>(.*?)</p>~is', $html, $m) ? $m[1] : $html;
        $body = preg_replace('~<br\s*/?>~i', "\n", $body);
        $body = strip_tags($body);
        $body = html_entity_decode($body, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($body);
    }
}
