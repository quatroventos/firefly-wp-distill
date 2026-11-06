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
        $this->app->bind(\Firefly\OpenSeo\OpenSeoClient::class, function () {
            $url = (string) config('ffd.openseo_url');
            $token = (string) config('ffd.openseo_token');
            if ($url === '' || $token === '') {
                return new \Firefly\OpenSeo\FakeOpenSeoClient();
            }
            return new \Firefly\OpenSeo\HttpOpenSeoClient($url, $token);
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
