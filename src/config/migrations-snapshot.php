<?php

return [
    'path' => 'database',
    'maps' => [

        'types' => [
            'tinyint' => 'boolean(',
            'smallint' => 'smallInteger',
            'bigint' => 'bigInteger',
            'int' => 'integer',
            'unsigned' => 'unsigned',
            'timestamp' => 'timestamp',
            'varchar' => 'string',
            'text' => 'text',
            'longtext' => 'longText',
            'date' => 'date',
            'datetime' => 'datetime',
            'decimal' => 'decimal',
            'double' => 'double',
            'json' => 'json',
        ],

        'extras' => [
            'auto_increment' => 'autoIncrement',
            'DEFAULT_GENERATED' => 'generatedAs',
        ],

        'keys' => [
            'PRI' => 'primary',
            'UNI' => 'unique'
        ],

        'nulls' => [
            'YES' => 'nullable',
            'NO' => '',
            null => ''
        ],

        'defaults' => [
            'CURRENT_TIMESTAMP' => 'useCurrent',
        ]


//        'pending' => '',
    ]
];
