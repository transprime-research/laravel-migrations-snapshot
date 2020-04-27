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
        $type = explode(' ', $column->Type);
        $data = '$table->';

        if ($column->Extra) {
            return $data.$this->typeMaps($column->Extra) . "('" . $column->Field . "');";
        }

        $data .= $this->typeMaps($type[0]) . "('" . $column->Field . "')";

        if (isset($type[1])) {
            $data .= $this->typeMaps($type[1]).'();';
        } else {
            $data .= ';';
        }

        return $data;
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

    public function typeMaps($field): string
    {
        $fields = [
            'bigint' => 'bigInteger',
            'int(11)' => 'bigInteger',
            'int' => 'integer',
            'bigint(20)' => 'integer',
            'unsigned' => 'unsigned',
            'auto_increment' => 'increments',
            'timestamp' => 'timestamp',
            'YES' => 'nullable', // nullable
            'varchar' => 'string',
            'varchar(255)' => 'string',
            'varchar(100)' => 'string',
            'text' => 'text',
            'longtext' => 'longText'
        ];

        return $fields[$field];
    }
}
