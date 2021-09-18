<?php

namespace Casanova\Manifold\Api\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Casanova\Manifold\Api\Core\Controllers\RSAController;

class RSAServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(RSAController::class, function ($app) {
            return new RSAController();
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
    }
}
