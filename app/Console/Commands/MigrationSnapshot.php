<?php

namespace App\Console\Commands;

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
use Transprime\MigrationSnapshot\Utils\CreateSchema;
use Transprime\MigrationSnapshot\Utils\ForeignSchema;
use Transprime\MigrationSnapshot\Utils\GetTables;
use Transprime\Piper\Piper;

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
    }

    public function handle(GetTables $getTables, CreateSchema $createSchema)
    {
        //see: https://doc.nette.org/en/3.0/php-generator

        //todo.update migrate in the order of migrations file
        $tablesFromFile = $getTables->fromFiles();

        \piper($getTables->fromDB($this->database))
            ->to('array_merge_recursive_distinct', $tablesFromFile)
            ->to('array_values')
            ->to('array_map', fn($table) => $createSchema->make($this->connection)->up($table))
            ->up();
    }
}
