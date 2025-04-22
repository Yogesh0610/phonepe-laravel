<?php

namespace Yogeshgupta\PhonepeLaravel;

use Illuminate\Support\ServiceProvider;

class PhonePeServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('phonepe.payment', function ($app) {
            return new \Yogeshgupta\PhonepeLaravel\PhonePePayment();
        });
    }

    public function boot()
    {
        $this->publishes([
            __DIR__.'/../config/phonepe.php' => config_path('phonepe.php'),
        ], 'phonepe-config');

        $this->mergeConfigFrom(__DIR__.'/../config/phonepe.php', 'phonepe');
    }
}
