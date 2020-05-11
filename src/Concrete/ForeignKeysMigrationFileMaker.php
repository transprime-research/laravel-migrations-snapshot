<?php

namespace Transprime\MigrationsSnapshot\Concrete;

use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;
use Transprime\MigrationsSnapshot\Interfaces\FileMakerInterface;

class ForeignKeysMigrationFileMaker implements FileMakerInterface
{
    /**
     * @var PhpFile $phpFile
     */
    private $phpFile;

    /**
     * @var ClassType $class
     */
    private $class;

    /**
     * @var PsrPrinter $printer
     */
    private $printer;

    public function __construct(PhpFile $phpFile, PsrPrinter $printer)
    {
        $this->phpFile = $phpFile;
        $this->printer = $printer;
    }

    public function createFile()
    {
        $this->phpFile->addUse('Illuminate\Database\Schema\Blueprint');
        $this->phpFile->addUse('Illuminate\Support\Facades\Schema');
        $this->phpFile->addUse('Illuminate\Database\Migrations\Migration');

        return $this;
    }

    public function createClass(string $create_name, string $table_name)
    {
        $this->class = $this->phpFile->addClass($create_name);

        $this->class->addExtend('Migration');
        $this->class->addComment("Migration for $table_name table");

        return $this;
    }

    public function getClass()
    {
        return $this->class;
    }

    /**
     * @inheritDoc
     */
    public function createUpMethod(string $table_name, string $closure = null)
    {
        $this->class->addMethod('up')
            ->setVisibility('public')
            ->addComment('Run the migrations')
            ->setBody(
                'Schema::table(\'' . $table_name . '\', ' . $closure . ');'
            );

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function createDownMethod(string $table_name, string $closure = null)
    {
        $this->class->addMethod('down')
            ->setVisibility('public')
            ->addComment('Reverse the migrations')
            ->setBody(
                'Schema::table(\'' . $table_name . '\', ' . $closure . ');',
            );

        return $this;
    }

    public function makeClosure()
    {
        $closure = new Closure();
        $closure->addParameter('table')
            ->setType('BluePrint');

        return $closure;
    }

    /**
     * @inheritDoc
     */
    public function saveFile(string $fullPath)
    {
        $printer = $this->printer->printFile($this->phpFile);

        file_put_contents($fullPath, $printer);

        return true;
    }
}
