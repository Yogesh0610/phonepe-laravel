<?php

namespace Yogeshgupta\PhonepeLaravel;

use Illuminate\Support\ServiceProvider;
use Yogeshgupta\PhonepeLaravel\PhonePePayment;

class PhonePeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/../config/phonepe.php' => config_path('phonepe.php'),
        ], 'phonepe-config');

        // Publish migrations
        $this->publishes([
            __DIR__ . '/../database/migrations/' => database_path('migrations'),
        ], 'phonepe-migrations');

        // Publish routes
        $this->publishes([
            __DIR__ . '/../routes/webhook.php' => base_path('routes/phonepe-webhook.php'),
        ], 'phonepe-routes');

        // Publish Events & Listeners
        $this->publishes([
            __DIR__ . '/Events' => app_path('Events/PhonePe'),
            __DIR__ . '/Listeners' => app_path('Listeners/PhonePe'),
        ], 'phonepe-events-listeners');

        // Load webhook route (fallback if not published)
        $customRoute = base_path('routes/phonepe-webhook.php');
        $this->loadRoutesFrom(file_exists($customRoute) ? $customRoute : __DIR__ . '/../routes/webhook.php');
    }

    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__ . '/../config/phonepe.php', 'phonepe');

        // Bind the main class — THIS IS REQUIRED!
        $this->app->singleton(PhonePePayment::class, function () {
            return new PhonePePayment();
        });

        // Also bind as 'phonepe' for backward compatibility
        $this->app->singleton('phonepe', function () {
            return new PhonePePayment();
        });
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides()
    {
        return [
            PhonePePayment::class,
            'phonepe',
        ];
    }
}