<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class MigrationSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'migration:snapshot';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
//        //see: https://doc.nette.org/en/3.0/php-generator
//        $file = new PhpFile();
//        $namespace = $file->addNamespace('Database\\Snapshots');
//        $class = $namespace->addClass('Demo');
//
//        $class
//            ->setFinal()
//            ->addComment("Description of class.\nSecond line\n")
//            ->addComment('@property-read Nette\Forms\Form $form');
//
//        $printer = (new PsrPrinter())->printFile($file);
//
//        $path = database_path('snapshots');
//
//        file_put_contents($path.'/demo1.php', $printer);


//        foreach (Storage::files('migrations') as $file) {
//
//            if (strripos($file,'.php') !== false) {
//
//                $data[$file] = $this->strpos_recursive(Storage::get($file), 'Schema::');
//            }
//        }
//        foreach (Storage::files('migrations') as $file) {
//
//            if (strripos($file,'.php') !== false) {
//
//                $pattern = "/Schema::\w+\(.*?\)/";
////                $pattern = "/(?<=\bSchema\s)([a-zA-Z-]+)";
//                preg_match_all($pattern, Storage::get($file), $matches);
//                foreach ($matches[0] as $match) {
//                    $data[$file][] = explode('(', $match)[1];
//                };
//            }
//        }

//        dump($data);


        $conn = config('database.default');
        dump(Schema::Connection($conn)->getColumnListing('users'));
        $database = config("database.connections.$conn.database");

        collect(Schema::getAllTables())
            ->pluck("Tables_in_$database")
            ->diff(['migrations'])
            ->each(function ($table) use ($conn) {
                $this->createSchema($conn, $table);
            });
    }

    private function createSchema(string $conn, string $table)
    {
        if ($conn === 'mysql') {
            $schema = DB::select(\DB::raw("SHOW COLUMNS FROM $table"));
        } else {
            $schema = Schema::parseSchemaAndTable($table);
        }

        $create_name = "create_${table}_table";

        $file_name = now()->format('Y_m_d_His') . "_${create_name}.php";

        dump("Creating: ${file_name}_${create_name}.php");
        $file = $this->createFile();

        $class = $this->createClass($file, $create_name, $table);

        $closure = $this->makeUpClosure(collect($schema));

        $this->createUpMethod($class, $table, $closure);
        $this->createDownMethod($class, $table);

        $printer = (new PsrPrinter())->printFile($file);

        $path = database_path('snapshots');

        file_put_contents($path . "/{$file_name}", $printer);
    }


    public function makeUpClosure(Collection $collect): Closure
    {
        $closure = $this->addClosure();

        $collect->each(function (\stdClass $column) use (&$closure) {
            $this->addToClosure($closure, $this->createColumn($column));
        });

        return $closure;
    }

    private function createColumn(\stdClass $column)
    {
//        dump($column);
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
            $data .= '->' . $this->typeMaps($column->Extra) . '()';
        }

        if ($column->Default) {
            if ($this->existsInMaps($column->Default)) {
                $data .= '->' . $this->typeMaps($column->Default) . '()';
            } else {
                $data .= "->default('".$column->Default.")'";
            }
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

        return $file;
    }

    private function addClosure()
    {
        $closure = new Closure();
        $closure->addParameter('table')
            ->setType('BluePrint');

        return $closure;
    }

    private function typeMaps($field): string
    {
        $fields = config('migrations-snapshot.maps');

        return $fields[$field];
    }

    private function existsInMaps($field)
    {
        return config("migrations-snapshot.maps.$field") != null;
    }
}
