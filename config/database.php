<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type'     => 'mysql',
            'hostname' => env('MYSQL_ADDON_HOST', env('DB_HOST', '127.0.0.1')),
            'database' => env('MYSQL_ADDON_DB',   env('DB_NAME', '')),
            'username' => env('MYSQL_ADDON_USER', env('DB_USER', 'root')),
            'password' => env('MYSQL_ADDON_PASSWORD', env('DB_PASS', '')),
            'hostport' => env('MYSQL_ADDON_PORT', env('DB_PORT', '3306')),
            'params'   => [],
            'charset'  => 'utf8mb4',
            'prefix'   => '',
            'deploy'   => 0,
            'rw_separate' => false,
            'fields_strict' => true,
            'break_reconnect' => false,
            'trigger_sql' => true,
            'fields_cache' => false,
        ],
    ],
];