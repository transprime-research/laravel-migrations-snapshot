<?php

namespace Transprime\MigrationsSnapshot\Utils;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Transprime\MigrationsSnapshot\Interfaces\FileMakerInterface;

class CreateNormalSchema
{
    /**
     * @var FileMakerInterface
     */
    private FileMakerInterface $fileMaker;

    public function __construct(FileMakerInterface $fileMaker)
    {
        $this->fileMaker = $fileMaker;
    }

    public function run(string $connection, string $table, string $create_name, string $fullPath)
    {
        if ($connection === 'mysql') {
            $schema = DB::select(\DB::raw("SHOW COLUMNS FROM $table"));
        } else {
            $schema = Schema::parseSchemaAndTable($table);
        }

        $create_name = Str::studly($create_name);

        $file = $this->fileMaker
            ->createFile()
            ->createClass($create_name, $table);

        $closure = $this->makeUpClosure(collect($schema));

        $file->createUpMethod($table, $closure)
            ->createDownMethod($table)
            ->saveFile($fullPath);

        return true;
    }

    /**
     * @param Collection $collect
     * @return Closure
     */
    private function makeUpClosure(Collection $collect): Closure
    {
        $closure = $this->fileMaker->makeClosure();

        $collect->each(function (\stdClass $column) use (&$closure) {
            $closure->addBody($closure, $this->createColumn($column));
        });

        return $closure;
    }

    private function createColumn(\stdClass $column)
    {
        $typeSizePattern = '/\(([^)]+)\)/';

        $type = explode(' ', $column->Type);

        preg_match($typeSizePattern, $type[0], $typeSize);
        $typeString = str_replace($typeSize[0] ?? '', '', $type[0]);

        $data = '$table->';

        $parametersString = $this->makeParameters($column->Field, $typeSize);

        $lastType = $this->typeMaps($typeString);

        if (strpos($lastType, '(') !== false) {
            $data .= "$lastType'$column->Field'" . ')';
        } else {
            $data .= $lastType . $parametersString;
        }

        if (isset($type[1])) {
            $data .= '->' . $this->typeMaps($type[1]) . '()';
        }

        if ($column->Extra) {
            $data .= '->' . $this->extraMaps($column->Extra) . '()';
        }

        if ($column->Default) {
            if ($this->existsInDefaults($column->Default)) {
                $data .= '->' . $this->defaultMaps($column->Default) . '()';
            } else {
                $data .= "->default('" . $column->Default . "')";
            }
        }

        if ($nullMapped = $this->nullMaps($column->Null)) {
            $data .= "->$nullMapped()";
        }

        if ($keyMapped = $this->keyMaps($column->Key)) {
            $data .= "->$keyMapped()";
        }

        return $data . ';';
    }

    private function makeParameters(string $columnField, array $typeSize)
    {
        $parametersString = "('$columnField'";

        if ($typeSize[1] ?? false) {
            $parametersString .= ", $typeSize[1]";
        }

        return "$parametersString)";
    }

    private function extraMaps($field): ?string
    {
        return config("migrations-snapshot.maps.extras.$field");
    }

    private function typeMaps($field): ?string
    {
        return config("migrations-snapshot.maps.types.$field");
    }

    private function defaultMaps($field): ?string
    {
        return config("migrations-snapshot.maps.defaults.$field");
    }

    private function nullMaps($field): ?string
    {
        return config("migrations-snapshot.maps.nulls.$field");
    }

    private function keyMaps($field): ?string
    {
        return config("migrations-snapshot.maps.keys.$field");
    }

    private function existsInDefaults($field): ?string
    {
        return config("migrations-snapshot.maps.defaults.$field") != null;
    }
}
