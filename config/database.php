'mysql' => [
    'type'            => env('DB_TYPE', 'mysql'),

    'hostname'        => env('MYSQLHOST',     env('DB_HOST', '127.0.0.1')),
    'database'        => env('MYSQLDATABASE', env('DB_NAME', '')),
    'username'        => env('MYSQLUSER',     env('DB_USER', 'root')),
    'password'        => env('MYSQLPASSWORD', env('DB_PASS', '')),
    'hostport'        => env('MYSQLPORT',     env('DB_PORT', '3306')),

    'params'          => [],
    'charset'         => env('DB_CHARSET', 'utf8mb4'),
    'prefix'          => env('DB_PREFIX', ''),

    'deploy'          => 0,
    'rw_separate'     => false,
    'master_num'      => 1,
    'slave_no'        => '',
    'fields_strict'   => true,
    'break_reconnect' => false,
    'trigger_sql'     => env('APP_DEBUG', true),
    'fields_cache'    => false,
],