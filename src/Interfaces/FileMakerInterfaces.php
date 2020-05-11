<?php

namespace Transprime\MigrationsSnapshot\Interfaces;

use Illuminate\Support\Collection;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;

interface FileMakerInterfaces
{
    /**
     * @return FileMakerInterfaces
     */
    public function createFile();

    /**
     * @param string $create_name
     * @param string $table_name
     * @return FileMakerInterfaces
     */
    public function createClass(string $create_name, string $table_name);

    /**
     * @param string $table_name
     * @param string $closure
     * @return FileMakerInterfaces
     */
    public function createUpMethod(string $table_name, string $closure = null);

    /**
     * @param string $table_name
     * @param string|null $closure
     * @return FileMakerInterfaces
     */
    public function createDownMethod(string $table_name, string $closure = null);

    /**
     * @return Closure
     */
    public function makeClosure();

    /**
     * @param string $fullPath
     * @return FileMakerInterfaces
     */
    public function saveFile(string $fullPath);
}
