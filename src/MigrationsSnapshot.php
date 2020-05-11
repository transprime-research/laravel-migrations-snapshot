<?php

namespace Transprime\MigrationsSnapshot;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Transprime\MigrationsSnapshot\Utils\CreateNormalSchema;

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
        $this->database = config('database.connections.' . $this->connection . '.database');
        $this->doctrineConnection = DB::connection($this->connection)->getDoctrineSchemaManager();
    }

    /**
     * Execute the console command.
     *
     * @param CreateNormalSchema $createNormalSchema
     * @return mixed
     * @throws \Transprime\Piper\Exceptions\PiperException
     */
    public function handle(CreateNormalSchema $createNormalSchema)
    {
        //see: https://doc.nette.org/en/3.0/php-generator

        $availableTables = collect(Schema::getAllTables())
            ->pluck('Tables_in_' . $this->database)
            ->diff(['migrations'])
            ->values()
            ->all();

        //todo.update migrate in the order of migrations file

        $timestamp = now()->format('Y_m_d_His');
        $path = config('migrations-snapshot.path') . "/snapshots/batch_$timestamp";

        $this->alert("Migrations snapshot starting: " . count($availableTables) . ' tables total');

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            $this->alert("Created directory: $path");
        }

        $this->info("Using directory: $path");

        \piper($availableTables)
            ->to('array_values')
            ->to('array_map', function ($table) use ($timestamp, $path, $createNormalSchema) {
                $create_name = "create_${table}_table";
                $file_name = $timestamp . "_${create_name}.php";

                $this->alert("Creating: ${file_name}.php");

                $createNormalSchema->run($this->connection, $table, $create_name, "$path/$file_name");

                $this->info("Created: ${file_name}.php");

                return $table;
            })->up();

        $this->info("Migrations snapshot finished: " . count($availableTables) . ' tables total');
    }
}
