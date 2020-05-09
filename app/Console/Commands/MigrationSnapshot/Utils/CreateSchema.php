<?php

namespace Transprime\MigrationSnapshot\Utils;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class CreateSchema
{

    use GetConfig;

    private $doctrineConnection;

    /**
     * @var string
     */
    private string $connection;


    public function make(string $connection)
    {
        $this->connection = $connection;
        $this->doctrineConnection = DB::connection($this->connection)->getDoctrineSchemaManager();

        return $this;
    }

    public function up(string $table)
    {
        if ($this->connection === 'mysql') {
            $schema = DB::connection($this->connection)->select(\DB::raw("SHOW COLUMNS FROM $table"));
        } else {
            $schema = Schema::parseSchemaAndTable($table);
        }

        $create_name = "create_${table}_table";

        $file_name = now()->format('Y_m_d_His') . "_${create_name}.php";

        dump("Creating: ${file_name}.php");

        $file = $this->createFile();

        $class = $this->createClass($file, $create_name, $table);

        $closure = $this->makeUpClosure($table, collect($schema));

        $this->createUpMethod($class, $table, $closure);
        $this->createDownMethod($class, $table);

        $printer = (new PsrPrinter())->printFile($file);

        $path = database_path('snapshots');

        file_put_contents($path . "/{$file_name}", $printer);
    }

    private function makeUpClosure(string $table, Collection $collect): Closure
    {
        $closure = $this->addClosure();

        $collect->each(function (\stdClass $column) use (&$closure) {
            $this->addToClosure($closure, $this->createColumn($column));
        });

        foreach ($this->addForeignFields($table) as $foreign) {
            $this->addToClosure($closure, $foreign);
        }

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

    public function addToClosure(Closure $closure, string $content)
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

    private function createDownMethod(ClassType $class, string $table_name, string $closure = null)
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

    public function addForeignFields(string $table)
    {
        $foreignKeys = $this->doctrineConnection->listTableForeignKeys($table);

        $tableForeign = [];

        /**
         * @var ForeignKeyConstraint|null $foreignKey
         */
        foreach ($foreignKeys as $foreignKey) {
            $tableString = '$table';

            $foreignTableName = $foreignKey->getForeignTableName();
            $localColumnNames = $foreignKey->getLocalColumns();
            $foreignColumns = $foreignKey->getForeignColumns();
            $options = $foreignKey->getOptions();
            $name = $foreignKey->getName();
            $tableString .= "->foreign(['" . implode("',", $localColumnNames) . "'], '$name')"
                . "->references(['" . implode("',", $foreignColumns) . "'])"
                . "->on('$foreignTableName')";

            foreach ($options as $key => $action) {
                if ($action !== 'NO ACTION') {
                    $tableString .= "->$key('$action')";
                }
            }

            $tableForeign[] = $tableString.';';
            unset($tableString);
        }

        dump([$table => $tableForeign]);

        return $tableForeign;
    }
}
