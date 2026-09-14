<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\YouTubeService::class, function ($app) {
            $client = new \Google\Client();
            $client->setDeveloperKey(config('services.youtube.api_key'));
            $youtube = new \Google\Service\YouTube($client);
            return new \App\Services\YouTubeService($youtube);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
