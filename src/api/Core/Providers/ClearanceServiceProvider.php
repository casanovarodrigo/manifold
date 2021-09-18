<?php

namespace Casanova\Manifold\Api\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

use Casanova\Manifold\Api\Core\Controllers\ClearanceController;
use Casanova\Manifold\Api\Core\Controllers\CacheController as Cache;

class ClearanceServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ClearanceController::class, function ($app) {
            return new ClearanceController();
        });

    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        try {
            $moduleController = app()->make(ClearanceController::class);
            $roles = Cache::getRoles();
            $moduleController->init($roles);
        } catch (\Throwable $th) {
            // if not "role table doesnt exist" error
            if (!$th->getCode() === "42S02"){
                throw $th;
            }

        }
        
    }
}
