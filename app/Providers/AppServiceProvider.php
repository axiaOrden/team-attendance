<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->forceConfiguredUrl();
    }

    /**
     * Keep every generated URL (routes, assets, redirects) inside the
     * subdirectory the application is deployed to, even when Apache or a
     * reverse proxy reports a different document root.
     */
    protected function forceConfiguredUrl(): void
    {
        $url = config('app.url');

        if (! is_string($url) || ! str_starts_with($url, 'http')) {
            return;
        }

        URL::forceRootUrl($url);

        if (str_starts_with($url, 'https://')) {
            URL::forceScheme('https');
        }
    }
}
