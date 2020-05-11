<?php

namespace Transprime\MigrationsSnapshot\Utils;

use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Transprime\MigrationsSnapshot\Interfaces\FileMakerInterfaces;

class CreateForeignSchema
{
    /**
     * @var FileMakerInterfaces $fileMaker
     */
    private $fileMaker;

    public function __construct($fileMaker)
    {
        $this->fileMaker = $fileMaker;
    }

    /**
     * @param AbstractSchemaManager $schemaManager
     * @param string $table
     * @param string $create_name
     * @param string $fullPath
     * @return bool
     */
    public function run($schemaManager, string $table, string $create_name, string $fullPath)
    {
        $create_name = Str::studly($create_name);

        $file = $this->fileMaker
            ->createFile()
            ->createClass($create_name, $table);

        $foreignDeclarations = $this->createSchemaRows($schemaManager, $table);
        $upClosure = $this->makeUpClosure($foreignDeclarations);

        $downClosure = $this->makeDownClosure(array_keys($foreignDeclarations));

        $file->createUpMethod($table, $upClosure)
            ->createDownMethod($table, $downClosure)
            ->saveFile($fullPath);

        return true;
    }

    public function makeUpClosure(array $foreignDeclarations)
    {
        $closure = $this->fileMaker->makeClosure();

        foreach ($foreignDeclarations as $foreign) {
            $closure->addBody($foreign);
        }

        return $closure;
    }

    public function makeDownClosure(array $foreignKeysNames)
    {
        $closure = $this->fileMaker->makeClosure();

        foreach ($foreignKeysNames as $name) {
            $closure->addBody(
                '$table->dropForeign('.$name.'\');'
            );
        }

        return $closure;
    }

    /**
     * @param AbstractSchemaManager $schemaManager
     * @param string $table
     * @return array
     */
    private function createSchemaRows($schemaManager, string $table)
    {
        $foreignKeys = $schemaManager->listTableForeignKeys($table);

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

            $tableForeign[$name] = $tableString . ';';
            unset($tableString);
        }

        return $tableForeign;
    }
}
