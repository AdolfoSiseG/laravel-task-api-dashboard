<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
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
        $isProduction = $this->app->isProduction();

        // Behind Render's TLS-terminating proxy the container receives plain HTTP, so
        // force HTTPS URL generation in production; otherwise Vite/asset URLs are emitted
        // as http:// and the browser blocks them as mixed content.
        if ($isProduction) {
            URL::forceScheme('https');
        }

        // Fail loudly in development on N+1 lazy loads and on filling attributes that are
        // not mass assignable; stay silent in production so the demo never surfaces these.
        Model::preventLazyLoading(! $isProduction);
        Model::preventSilentlyDiscardingAttributes(! $isProduction);
    }
}
