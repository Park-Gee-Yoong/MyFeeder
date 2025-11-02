<?php

namespace ParkGeeYoong\MyFeeder;

use Illuminate\Support\ServiceProvider;

class NeoFeederServiceProvider extends ServiceProvider
{
    public function register()
    {
        // merge konfigurasi default package
        $this->mergeConfigFrom(__DIR__.'/../config/neofeeder.php', 'neofeeder');

        // singleton service
        $this->app->singleton(NeoFeederService::class, function ($app) {
            return new NeoFeederService();
        });
    }

    public function boot()
    {
        // publish config
        $this->publishes([
            __DIR__.'/../config/neofeeder.php' => config_path('neofeeder.php'),
        ], 'config');
    }
}

