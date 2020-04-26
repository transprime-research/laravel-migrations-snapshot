<?php

namespace App\Providers;

use Illuminate\Contracts\Database\Events\MigrationEvent;
use Illuminate\Database\Events\MigrationEnded;
use Illuminate\Database\Events\MigrationsStarted;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
//        DB::listen(function ($query) {
//            var_dump([
//                get_class($query),
//                $query->sql,
//                $query->bindings,
//                $query->time
//            ]);
//        });

//        DB::listen(function ($event) {
//
//            $cleaned = str_replace('`', '', $event->sql);
//            $cleaned = ltrim(substr($cleaned, strpos($cleaned, "create table") + 13));
//            dd(
//                explode(' ', $cleaned)[0]
////                str_replace('`', '\'', $event->sql)
//            );
//        });
    }
}
