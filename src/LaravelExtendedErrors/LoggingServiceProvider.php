<?php

namespace LaravelExtendedErrors;

use Illuminate\Support\ServiceProvider;

class LoggingServiceProvider extends ServiceProvider {

    public function boot() {
        $this->publishes([
            __DIR__ . '/config/logging.php' => config_path('logging.php'),
        ], 'config');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register() {

    }
}