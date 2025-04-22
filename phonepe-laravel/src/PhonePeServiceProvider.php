<?php

namespace YogeshGupta\PhonePe;

use Illuminate\Support\ServiceProvider;

class PhonePeServiceProvider extends ServiceProvider
{
    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . '/../config/phonepe.php' => config_path('phonepe.php'),
        ], 'phonepe-config');
    }

    public function register()
    {
        // Merge configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/phonepe.php', 'phonepe');

        // Register PhonePePayment as a singleton
        $this->app->singleton('phonepe', function () {
            return new PhonePePayment();
        });
    }
}
?>