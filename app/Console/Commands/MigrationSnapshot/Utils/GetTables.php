<?php

namespace Transprime\MigrationSnapshot\Utils;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Transprime\Piper\Piper;

class GetTables
{
    public function fromDB(string $databse)
    {
        return collect(Schema::getAllTables())
            ->pluck('Tables_in_'.$databse)
            ->diff(['migrations'])
            ->values()
            ->all();
    }

    public function fromFiles()
    {
        $data = [];
        foreach (Storage::files('migrations') as $file) {
            if (strripos($file, '.php') !== false) {
                $matches = $this->getFileContent($file);
                foreach ($matches[0] as $match) {
                    $data[$file][] = $this->getATable($match);
                }
            }
        }

        return Piper::on($data)
            ->to('array_map', fn($file) => $file[0])
            ->to('array_unique')
            ->to(fn($tables) => array_filter($tables, fn($table) => strripos($table, ' ') === false))
            ->to('array_values')();
    }

    private function getFileContent($file)
    {
        return piper($file)
            ->to([Storage::class, 'get'])
            ->to(function ($content) {
                preg_match_all("/Schema::\w+\(.*?\)/", $content, $matches);

                return $matches;
            })();
    }

    private function getATable(string $match)
    {
        return piper($match)
            ->to('explode', '(')
            ->to(fn($data) => $data[1])
            ->to('str_replace', [')', 'function', '\'', ','], '')
            ->to('trim')();
    }
}
