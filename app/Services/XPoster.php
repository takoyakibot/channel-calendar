<?php

namespace App\Services;

use Abraham\TwitterOAuth\TwitterOAuth;

/** Posts to X from the bot account using OAuth 1.0a user-context credentials. */
class XPoster
{
    private ?TwitterOAuth $client = null;

    public function isConfigured(): bool
    {
        $x = config('services.x');

        return ! empty($x['api_key']) && ! empty($x['api_secret'])
            && ! empty($x['access_token']) && ! empty($x['access_secret']);
    }

    /**
     * @return string the id of the created tweet
     *
     * @throws XPosterException when X does not create the tweet (code = HTTP status)
     */
    public function post(string $text): string
    {
        if (! $this->isConfigured()) {
            throw new XPosterException('X credentials are not configured.');
        }

        $client = $this->client();
        $response = $client->post('tweets', ['text' => $text], ['jsonPayload' => true]);
        $status = $client->getLastHttpCode();

        $id = is_object($response) ? ($response->data->id ?? null) : ($response['data']['id'] ?? null);
        if ($status !== 201 || ! $id) {
            throw new XPosterException($this->describeError($response, $status), $status);
        }

        return (string) $id;
    }

    private function client(): TwitterOAuth
    {
        if ($this->client === null) {
            $x = config('services.x');
            $this->client = new TwitterOAuth($x['api_key'], $x['api_secret'], $x['access_token'], $x['access_secret']);
            $this->client->setApiVersion('2');
            $this->client->setTimeouts(10, 15);
        }

        return $this->client;
    }

    private function describeError(mixed $response, int $status): string
    {
        $r = json_decode(json_encode($response), true) ?: [];
        $detail = $r['detail'] ?? $r['title'] ?? ($r['errors'][0]['message'] ?? null);

        return $detail ? "HTTP {$status}: {$detail}" : "HTTP {$status}";
    }
}
