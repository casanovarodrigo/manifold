<?php

namespace Casanova\Manifold\Api\Core\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
// use Illuminate\Database\Eloquent\Factory as Factory;
// use Faker\Generator as Faker;

use Casanova\Manifold\Api\Core\Controllers\ModuleController;

class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->singleton(ModuleController::class, function ($app) {
            return new ModuleController();
        });

        // $this->app->singleton(Factory::class, function ($app) {
        //     $moduleController = app()->make(ModuleController::class);
        //     $path = $moduleController->getCoreFactories();
        //     return Factory::construct(new Faker, $path);
        // });


    }

    /**
     * Bootstrap services.
     *
     * @return void
     */
    public function boot()
    {
        $modulesList = config('modules.list');
        $moduleController = app()->make(ModuleController::class);
        // use enlist modules when modules outside of core are introduced
        // $moduleController->enlistModules($modulesList);
        $moduleController->validateCore();
        $moduleController->registerCoreRoutes();
        $coreMigrationPaths = $moduleController->getCoreMigrationPaths();
        $this->loadMigrationsFrom($coreMigrationPaths);
        $moduleController->registerCoreResourceMiddlewares();
    }
}
