<?php

//        $tables = collect(Schema::Connection($conn)->getAllTables())
//            ->map(function ($table) use ($conn) {
//                return $table->{'Tables_in_' . config("database.connections.$conn.database")};
//            })->map(function ($table) use ($conn) {
//
//
//                dump(Schema::Connection($conn)->getColumnListing($table));
////                    ->map(function ($column) use ($conn, $table){
////                        dump(Schema::Connection($conn)->getColumnType($table, $column));
////                    });
//            });
