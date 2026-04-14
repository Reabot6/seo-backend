<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'type'            => 'mysql',
            'hostname'        => env('MYSQL_ADDON_HOST', '127.0.0.1'),
            'database'        => env('MYSQL_ADDON_DB', ''),
            'username'        => env('MYSQL_ADDON_USER', 'root'),
            'password'        => env('MYSQL_ADDON_PASSWORD', ''),
            'hostport'        => env('MYSQL_ADDON_PORT', '3306'),
            'params'          => [],
            'charset'         => 'utf8mb4',
            'prefix'          => '',
            'deploy'          => 0,
            'rw_separate'     => false,
            'master_num'      => 1,
            'slave_no'        => '',
            'fields_strict'   => true,
            'break_reconnect' => false,
            'trigger_sql'     => false,
            'fields_cache'    => false,
        ],
    ],
];