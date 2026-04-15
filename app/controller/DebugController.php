<?php
namespace app\controller;

use think\facade\Config;

class DebugController
{
    // ─────────────────────────────────────────
    // GET /api/debug/config
    // Returns resolved DB config, raw env vars,
    // and a live PDO connection test.
    // ─────────────────────────────────────────
    public function config()
    {
        // 1. Resolved database configuration (as ThinkPHP sees it)
        $dbConfig = Config::get('database.connections.mysql');

        $resolvedConfig = [
            'hostname' => $dbConfig['hostname'] ?? null,
            'hostport' => $dbConfig['hostport'] ?? null,
            'database' => $dbConfig['database'] ?? null,
            'username' => $dbConfig['username'] ?? null,
            // password intentionally omitted from output
        ];

        // 2. Raw environment variables
        $envVars = [
            'MYSQL_ADDON_HOST'     => getenv('MYSQL_ADDON_HOST')     ?: null,
            'MYSQL_ADDON_PORT'     => getenv('MYSQL_ADDON_PORT')     ?: null,
            'MYSQL_ADDON_DB'       => getenv('MYSQL_ADDON_DB')       ?: null,
            'MYSQL_ADDON_USER'     => getenv('MYSQL_ADDON_USER')     ?: null,
            'MYSQL_ADDON_PASSWORD' => getenv('MYSQL_ADDON_PASSWORD') !== false
                                        ? (getenv('MYSQL_ADDON_PASSWORD') === '' ? '(empty string)' : '(set)')
                                        : null,
            // Legacy fallbacks
            'DB_HOST'              => getenv('DB_HOST')     ?: null,
            'DB_PORT'              => getenv('DB_PORT')     ?: null,
            'DB_NAME'              => getenv('DB_NAME')     ?: null,
            'DB_USER'              => getenv('DB_USER')     ?: null,
            'DB_PASS'              => getenv('DB_PASS') !== false
                                        ? (getenv('DB_PASS') === '' ? '(empty string)' : '(set)')
                                        : null,
        ];

        // 3. Live PDO connection test
        $connectionTest = $this->testConnection($dbConfig);

        return json([
            'status'          => 'ok',
            'resolved_config' => $resolvedConfig,
            'env_vars'        => $envVars,
            'connection_test' => $connectionTest,
        ]);
    }

    // ─────────────────────────────────────────
    // Attempt a raw PDO connection using the
    // resolved config and return the result.
    // ─────────────────────────────────────────
    private function testConnection(array $cfg): array
    {
        $host     = $cfg['hostname'] ?? '127.0.0.1';
        $port     = $cfg['hostport'] ?? '3306';
        $dbname   = $cfg['database'] ?? '';
        $username = $cfg['username'] ?? '';
        $password = $cfg['password'] ?? '';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

        try {
            $pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_TIMEOUT            => 5,
                \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);

            // Quick sanity query
            $row = $pdo->query('SELECT VERSION() AS version')->fetch();

            return [
                'success'        => true,
                'mysql_version'  => $row['version'] ?? 'unknown',
                'dsn_template'   => "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            ];
        } catch (\PDOException $e) {
            return [
                'success'      => false,
                'error_code'   => $e->getCode(),
                'error_message'=> $e->getMessage(),
                'dsn_template' => "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4",
            ];
        }
    }
}
