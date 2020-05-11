<?php

namespace Transprime\MigrationsSnapshot\Utils;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Transprime\MigrationsSnapshot\Interfaces\FileMakerInterfaces;

class CreateNormalSchema
{
    /**
     * @var FileMakerInterfaces
     */
    private FileMakerInterfaces $fileMaker;

    public function __construct(FileMakerInterfaces $fileMaker)
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
     * @param FileMakerInterfaces $fileMaker
     * @param Collection $collect
     * @return Closure
     */
    private function makeUpClosure($fileMaker, Collection $collect): Closure
    {
        $closure = $fileMaker->makeClosure();

        $collect->each(function (\stdClass $column) use (&$closure) {
            $this->addToClosure($closure, $this->createColumn($column));
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

    private function addToClosure(Closure $closure, string $content)
    {
        return $closure->addBody($content);
    }

    private function createUpMethod(ClassType $class, string $table_name, string $closure)
    {
        return $class->addMethod('up')
            ->setVisibility('public')
            ->addComment('Run the migrations')
            ->setBody(
                'Schema::create(\'' . $table_name . '\', ' . $closure . ');'
            );
    }

    private function createClass(PhpFile $file, string $create_name, string $table_name)
    {
        $class = $file->addClass(Str::studly($create_name));

        $class->addExtend('Migration');
        $class->addComment("Migration for $table_name table");

        return $class;
    }

    private function createDownMethod(ClassType $class, string $table_name)
    {
        return $class->addMethod('down')
            ->setVisibility('public')
            ->addComment('Reverse the migrations')
            ->setBody(
                "Schema::dropIfExists('${table_name}');"
            );
    }

    private function createFile()
    {
        $file = new PhpFile();

        $file->addUse('Illuminate\Database\Schema\Blueprint');
        $file->addUse('Illuminate\Support\Facades\Schema');
        $file->addUse('Illuminate\Database\Migrations\Migration');

        return $file;
    }

    private function addClosure()
    {
        $closure = new Closure();
        $closure->addParameter('table')
            ->setType('BluePrint');

        return $closure;
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
