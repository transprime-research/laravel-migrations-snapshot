<?php

namespace Transprime\MigrationSnapshot\Utils;

trait GetConfig
{
    public function extraMaps($field): ?string
    {
        return config("migrations-snapshot.maps.extras.$field");
    }

    public function typeMaps($field): ?string
    {
        return config("migrations-snapshot.maps.types.$field");
    }

    public function defaultMaps($field): ?string
    {
        return config("migrations-snapshot.maps.defaults.$field");
    }

    public function nullMaps($field): ?string
    {
        return config("migrations-snapshot.maps.nulls.$field");
    }

    public function keyMaps($field): ?string
    {
        return config("migrations-snapshot.maps.keys.$field");
    }

    public function existsInDefaults($field): ?string
    {
        return config("migrations-snapshot.maps.defaults.$field") != null;
    }
}
