<?php

namespace Transprime\MigrationsSnapshot;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Transprime\MigrationsSnapshot\Utils\CreateForeignSchema;
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

    /**
     * @var AbstractSchemaManager $doctrineSchemaManager
     */
    private $doctrineSchemaManager;

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
        $this->doctrineSchemaManager = DB::connection($this->connection)->getDoctrineSchemaManager();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     * @throws \Transprime\Piper\Exceptions\PiperException
     */
    public function handle()
    {
        $availableTables = $this->getTables();

        [$path, $timestamp] = $this->preparePath();

        $this->alert("Migrations snapshot starting: " . count($availableTables) . ' tables total');

        $this->info("Using directory: $path");

        piper($availableTables)
            ->to('array_values')
            ->to('array_map', function ($table) use ($timestamp, $path) {
                $this->makeCreateFile(app(CreateNormalSchema::class), $table, $path, $timestamp);

                return $table;
            })->to('array_map', function ($table) use ($timestamp, $path) {
                $this->makeForeignKeysFile(app(CreateForeignSchema::class), $table, $path, $timestamp);

                return $table;
            })->up();

        $this->info("Migrations snapshot finished: " . count($availableTables) . ' tables total');
    }

    private function getTables()
    {
        return collect(Schema::getAllTables())
            ->pluck('Tables_in_' . $this->database)
            ->diff(['migrations'])
            ->values()
            ->all();;
    }

    private function preparePath()
    {
        //todo.update migrate in the order of migrations file

        $timestamp = now()->format('Y_m_d_His');
        $path = config('migrations-snapshot.path') . "/snapshots/batch_$timestamp";

        if (!file_exists($path)) {
            mkdir($path, 0777, true);
            $this->alert("Created directory: $path");
        }

        return [$path, $timestamp];
    }

    /**
     * @param CreateNormalSchema $createNormalSchema
     * @param string $table
     * @param string $path
     * @param string $timestamp
     */
    private function makeCreateFile($createNormalSchema, string $table, string $path, string $timestamp)
    {
        $create_name = "create_${table}_table";
        $file_name = $timestamp . "_${create_name}.php";

        $this->alert("Creating: ${file_name}");

        $createNormalSchema->run($this->connection, $table, $create_name, "$path/$file_name");

        $this->info("Created: ${file_name}.php");
    }

    /**
     * @param CreateForeignSchema $foreignSchema
     * @param string $table
     * @param string $path
     * @param string $timestamp
     */
    private function makeForeignKeysFile($foreignSchema, string $table, string $path, string $timestamp)
    {
        $create_name = "update_${table}_table_add_foreign_keys";
        $file_name = $timestamp . "_${create_name}.php";

        $this->alert("Creating: ${file_name}.php");

        $foreignSchema->run($this->doctrineSchemaManager, $table, $create_name, "$path/$file_name");

        $this->info("Created: ${file_name}.php");
    }
}
