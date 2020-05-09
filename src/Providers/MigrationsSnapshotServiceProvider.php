<?php

namespace Transprime\MigrationsSnapshot\Providers;

use Illuminate\Support\ServiceProvider;
use Transprime\MigrationsSnapshot\MigrationsSnapshot;

class MigrationsSnapshotServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__.'/../../config/migrations-snapshot.php' => config_path('migrations-snapshot.php'),
        ]);

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrationsSnapshot::class,
            ]);
        }


    }
}
