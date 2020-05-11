<?php

namespace Transprime\MigrationsSnapshot\Providers;

use Illuminate\Support\ServiceProvider;
use Transprime\MigrationsSnapshot\Concrete\CreateMigrationFileMaker;
use Transprime\MigrationsSnapshot\Concrete\ForeignKeysMigrationFileMaker;
use Transprime\MigrationsSnapshot\Interfaces\FileMakerInterface;
use Transprime\MigrationsSnapshot\MigrationsSnapshot;
use Transprime\MigrationsSnapshot\Utils\CreateForeignSchema;

class MigrationsSnapshotServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->when(CreateForeignSchema::class)
            ->needs(FileMakerInterface::class)
            ->give(CreateMigrationFileMaker::class);

        $this->app->when(CreateForeignSchema::class)
            ->needs(FileMakerInterface::class)
            ->give(ForeignKeysMigrationFileMaker::class);

        $this->publishes([
            __DIR__.'/../../config/migrations-snapshot.php' => config_path('migrations-snapshot.php'),
        ], 'migrations-snapshot');

        if ($this->app->runningInConsole()) {
            $this->commands([
                MigrationsSnapshot::class,
            ]);
        }
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/migrations-snapshot.php', 'laravel-migrations-snapshot');
    }
}
