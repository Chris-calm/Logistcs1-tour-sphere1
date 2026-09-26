<?php

if (!function_exists('env')) {
    function env($key, $default = null) {
        static $env = null;

        if ($env === null) {
            $env = [];
            $envFile = dirname(__DIR__) . '/.env';
            if (is_file($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    $trimmed = trim($line);
                    if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                        continue;
                    }

                    $parts = explode('=', $line, 2);
                    if (count($parts) === 2) {
                        $env[trim($parts[0])] = trim($parts[1]);
                    }
                }
            }
        }

        $value = getenv($key);
        if ($value !== false && $value !== null) {
            return $value;
        }

        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        return $default;
    }
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$rootDir = dirname(__DIR__);

$env = [];
$envFile = $rootDir . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $env[trim($parts[0])] = trim($parts[1]);
        }
    }
}

$driver = getenv('DB_CONNECTION') ?: ($env['DB_CONNECTION'] ?? 'sqlite');
$host = getenv('DB_HOST') ?: ($env['DB_HOST'] ?? '127.0.0.1');
$port = getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '3306');
$dbName = getenv('DB_DATABASE') ?: ($env['DB_DATABASE'] ?? $rootDir . '/database/database.sqlite');
$username = getenv('DB_USERNAME') ?: ($env['DB_USERNAME'] ?? 'root');
$password = getenv('DB_PASSWORD') ?: ($env['DB_PASSWORD'] ?? '');

$pdo = null;
try {
    if (strtolower($driver) === 'sqlite') {
        $sqlitePath = $dbName;
        if (!is_file($sqlitePath)) {
            $sqlitePath = $rootDir . '/database/database.sqlite';
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } else {
        $dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $dbName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
} catch (Throwable $e) {
    $fallbackSqlite = $rootDir . '/database/database.sqlite';
    if (is_file($fallbackSqlite)) {
        try {
            $pdo = new PDO('sqlite:' . $fallbackSqlite, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (Throwable $ignored) {
            $pdo = null;
        }
    }
}

require_once $rootDir . '/includes/function.php';

$GLOBALS['pdo'] = $pdo;

$config = [
    'default' => $driver,
    'connections' => [
        'sqlite' => [
            'driver' => 'sqlite',
            'url' => getenv('DB_URL') ?: ($env['DB_URL'] ?? null),
            'database' => $dbName,
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => null,
            'journal_mode' => null,
            'synchronous' => null,
            'transaction_mode' => 'DEFERRED',
        ],
        'mysql' => [
            'driver' => 'mysql',
            'url' => getenv('DB_URL') ?: ($env['DB_URL'] ?? null),
            'host' => $host,
            'port' => $port,
            'database' => $dbName,
            'username' => $username,
            'password' => $password,
            'unix_socket' => getenv('DB_SOCKET') ?: ($env['DB_SOCKET'] ?? ''),
            'charset' => getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8mb4'),
            'collation' => getenv('DB_COLLATION') ?: ($env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ],
        'mariadb' => [
            'driver' => 'mariadb',
            'url' => getenv('DB_URL') ?: ($env['DB_URL'] ?? null),
            'host' => $host,
            'port' => $port,
            'database' => $dbName,
            'username' => $username,
            'password' => $password,
            'unix_socket' => getenv('DB_SOCKET') ?: ($env['DB_SOCKET'] ?? ''),
            'charset' => getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8mb4'),
            'collation' => getenv('DB_COLLATION') ?: ($env['DB_COLLATION'] ?? 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => [],
        ],
        'pgsql' => [
            'driver' => 'pgsql',
            'url' => getenv('DB_URL') ?: ($env['DB_URL'] ?? null),
            'host' => $host,
            'port' => getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '5432'),
            'database' => $dbName,
            'username' => $username,
            'password' => $password,
            'charset' => getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => getenv('DB_SSLMODE') ?: ($env['DB_SSLMODE'] ?? 'prefer'),
        ],
        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => getenv('DB_URL') ?: ($env['DB_URL'] ?? null),
            'host' => $host,
            'port' => getenv('DB_PORT') ?: ($env['DB_PORT'] ?? '1433'),
            'database' => $dbName,
            'username' => $username,
            'password' => $password,
            'charset' => getenv('DB_CHARSET') ?: ($env['DB_CHARSET'] ?? 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
        ],
    ],
    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],
    'redis' => [
        'client' => getenv('REDIS_CLIENT') ?: ($env['REDIS_CLIENT'] ?? 'phpredis'),
        'options' => [
            'cluster' => getenv('REDIS_CLUSTER') ?: ($env['REDIS_CLUSTER'] ?? 'redis'),
            'prefix' => getenv('REDIS_PREFIX') ?: ($env['REDIS_PREFIX'] ?? 'laravel_database_'),
            'persistent' => false,
        ],
        'default' => [
            'url' => getenv('REDIS_URL') ?: ($env['REDIS_URL'] ?? null),
            'host' => getenv('REDIS_HOST') ?: ($env['REDIS_HOST'] ?? '127.0.0.1'),
            'username' => getenv('REDIS_USERNAME') ?: ($env['REDIS_USERNAME'] ?? null),
            'password' => getenv('REDIS_PASSWORD') ?: ($env['REDIS_PASSWORD'] ?? null),
            'port' => getenv('REDIS_PORT') ?: ($env['REDIS_PORT'] ?? '6379'),
            'database' => getenv('REDIS_DB') ?: ($env['REDIS_DB'] ?? '0'),
            'max_retries' => getenv('REDIS_MAX_RETRIES') ?: ($env['REDIS_MAX_RETRIES'] ?? 3),
            'backoff_algorithm' => getenv('REDIS_BACKOFF_ALGORITHM') ?: ($env['REDIS_BACKOFF_ALGORITHM'] ?? 'decorrelated_jitter'),
            'backoff_base' => getenv('REDIS_BACKOFF_BASE') ?: ($env['REDIS_BACKOFF_BASE'] ?? 100),
            'backoff_cap' => getenv('REDIS_BACKOFF_CAP') ?: ($env['REDIS_BACKOFF_CAP'] ?? 1000),
        ],
        'cache' => [
            'url' => getenv('REDIS_URL') ?: ($env['REDIS_URL'] ?? null),
            'host' => getenv('REDIS_HOST') ?: ($env['REDIS_HOST'] ?? '127.0.0.1'),
            'username' => getenv('REDIS_USERNAME') ?: ($env['REDIS_USERNAME'] ?? null),
            'password' => getenv('REDIS_PASSWORD') ?: ($env['REDIS_PASSWORD'] ?? null),
            'port' => getenv('REDIS_PORT') ?: ($env['REDIS_PORT'] ?? '6379'),
            'database' => getenv('REDIS_CACHE_DB') ?: ($env['REDIS_CACHE_DB'] ?? '1'),
            'max_retries' => getenv('REDIS_MAX_RETRIES') ?: ($env['REDIS_MAX_RETRIES'] ?? 3),
            'backoff_algorithm' => getenv('REDIS_BACKOFF_ALGORITHM') ?: ($env['REDIS_BACKOFF_ALGORITHM'] ?? 'decorrelated_jitter'),
            'backoff_base' => getenv('REDIS_BACKOFF_BASE') ?: ($env['REDIS_BACKOFF_BASE'] ?? 100),
            'backoff_cap' => getenv('REDIS_BACKOFF_CAP') ?: ($env['REDIS_BACKOFF_CAP'] ?? 1000),
        ],
    ],
];

if (function_exists('env')) {
    return $config;
}

return $config;
