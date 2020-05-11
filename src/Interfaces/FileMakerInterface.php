<?php

namespace Transprime\MigrationsSnapshot\Interfaces;

use Nette\PhpGenerator\Closure;

interface FileMakerInterface
{
    /**
     * @return FileMakerInterface
     */
    public function createFile();

    /**
     * @param string $create_name
     * @param string $table_name
     * @return FileMakerInterface
     */
    public function createClass(string $create_name, string $table_name);

    /**
     * @param string $table_name
     * @param string $closure
     * @return FileMakerInterface
     */
    public function createUpMethod(string $table_name, string $closure = null);

    /**
     * @param string $table_name
     * @param string|null $closure
     * @return FileMakerInterface
     */
    public function createDownMethod(string $table_name, string $closure = null);

    /**
     * @return Closure
     */
    public function makeClosure();

    /**
     * @param string $fullPath
     * @return FileMakerInterface
     */
    public function saveFile(string $fullPath);
}
