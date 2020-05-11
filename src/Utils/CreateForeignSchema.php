<?php

namespace Transprime\MigrationsSnapshot\Utils;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Nette\PhpGenerator\Closure;

class CreateForeignSchema
{
    public function makeUpClosures(string $table)
    {
        $closure = $this->addClosure();

        foreach ($this->createForeignSchema($table) as $foreign) {
            $this->addToClosure($closure, $foreign);
        }
    }

    private function addClosure()
    {
        $closure = new Closure();
        $closure->addParameter('table')
            ->setType('BluePrint');

        return $closure;
    }


    private function addToClosure(Closure $closure, string $content)
    {
        return $closure->addBody($content);
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

            $tableForeign[] = $tableString . ';';
            unset($tableString);
        }

        return $tableForeign;
    }
}
