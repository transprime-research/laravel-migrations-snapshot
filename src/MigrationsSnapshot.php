<?php

namespace Transprime\MigrationsSnapshot;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Transprime\Piper\Piper;

class MigrationsSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migrations:snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Execute the migration snapshot';

    /**
     * @var \Illuminate\Config\Repository|string $connection
     */
    private $connection;

    /**
     * @var \Illuminate\Config\Repository|string $database
     */
    private $database;
    private $doctrineConnection;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->connection = config('database.default');
        $this->database = config('database.connections.'.$this->connection.'.database');
        $this->doctrineConnection = DB::connection($this->connection)->getDoctrineSchemaManager();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Transprime\Piper\Exceptions\PiperException
     */
    public function handle()
    {
        //see: https://doc.nette.org/en/3.0/php-generator

        $availableTables = collect(Schema::getAllTables())
            ->pluck('Tables_in_'.$this->database)
            ->diff(['migrations'])
            ->values()
            ->all();

        //todo.update migrate in the order of migrations file
        $tablesFromFile = $this->getTables();

        \piper($availableTables)
            ->to('array_merge_recursive_distinct', $tablesFromFile)
            ->to('array_values')
            ->to('array_map', fn($table) => $this->createSchema($table))
            ->up();

//        collect()
//            ->intersect()
//            ->each(function ($table) use ($conn) {
//                ;
//            });
    }

    private function getTables()
    {
        $initialPath = config('filesystem.disks.local.root');
        config()->set('filesystem.disks.local.root', database_path());
        $data = [];
        foreach (Storage::disk('local')->files('migrations') as $file) {
            if (strripos($file, '.php') !== false) {
                $matches = $this->getFileContent($file);
                foreach ($matches[0] as $match) {
                    $data[$file][] = $this->getATable($match);
                }
            }
        }

        config()->set('filesystem.disks.local.root', $initialPath);
        return Piper::on($data)
            ->to('array_map', fn($file) => $file[0])
            ->to('array_unique')
            ->to(fn($tables) => array_filter($tables, fn($table) => strripos($table, ' ') === false))
            ->to('array_values')();
    }

    private function getFileContent($file)
    {
        return piper($file)
            ->to([Storage::class, 'get'])
            ->to(function ($content) {
                preg_match_all("/Schema::\w+\(.*?\)/", $content, $matches);

                return $matches;
            })();
    }

    private function getATable(string $match)
    {
        return piper($match)
            ->to('explode', '(')
            ->to(fn($data) => $data[1])
            ->to('str_replace', [')', 'function', '\'', ','], '')
            ->to('trim')();
    }

    private function createSchema(string $table)
    {
        if ($this->connection === 'mysql') {
            $schema = DB::select(\DB::raw("SHOW COLUMNS FROM $table"));
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

        $path = config('migrations-snapshot.path').'/snapshots';

        if (!file_exists($path)) {
            mkdir($path);
        }

        file_put_contents($path . "/{$file_name}", $printer);
    }

    private function makeUpClosure(string $table, Collection $collect): Closure
    {
        $closure = $this->addClosure();

        $collect->each(function (\stdClass $column) use (&$closure) {
            $this->addToClosure($closure, $this->createColumn($column));
        });

        foreach ($this->createForeignSchema($table) as $foreign) {
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

    private function createForeignSchema(string $table)
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
