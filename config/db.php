<?php
// ============================================================================
// Database Connection (PDO + Prepared Statements)
// Supports .env files, environment variables, DATABASE_URL URIs, SSL, and local fallbacks
// ============================================================================

// Lightweight .env loader if .env file exists and no external library is used
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile) && is_readable($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($key, $val) = explode('=', $line, 2);
            $key = trim($key);
            $val = trim($val);
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            if (getenv($key) === false && !isset($_ENV[$key])) {
                putenv("$key=$val");
                $_ENV[$key] = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// Helper to fetch env variable with fallback
$getEnvVar = function ($keys, $default = '') {
    foreach ((array) $keys as $key) {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }
    }
    return $default;
};

// Check for cloud connection URIs (e.g. Railway, Render, Heroku: mysql://user:pass@host:port/dbname)
$dbUrl = $getEnvVar(['DATABASE_URL', 'MYSQL_URL', 'JAWSDB_URL', 'CLEARDB_DATABASE_URL']);

if (!empty($dbUrl) && str_contains($dbUrl, '://')) {
    $parsedUrl = parse_url($dbUrl);
    $host = $parsedUrl['host'] ?? '127.0.0.1';
    $port = $parsedUrl['port'] ?? '3306';
    $user = $parsedUrl['user'] ?? 'root';
    $pass = $parsedUrl['pass'] ?? '';
    $db   = ltrim($parsedUrl['path'] ?? 'inventory_db', '/');
} else {
    // Database settings with safe defaults for XAMPP / Laragon / Docker
    $host = $getEnvVar(['DB_HOST'], '127.0.0.1');
    $port = $getEnvVar(['DB_PORT'], '3306');
    $db   = $getEnvVar(['DB_NAME', 'DB_DATABASE'], 'inventory_db');
    $user = $getEnvVar(['DB_USER', 'DB_USERNAME'], 'root');
    $pass = $getEnvVar(['DB_PASS', 'DB_PASSWORD'], '');
}

$charset = 'utf8mb4';
$appDebug = strtolower((string) $getEnvVar(['APP_DEBUG'], 'false')) === 'true' || isset($_GET['debug']);

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
    PDO::ATTR_TIMEOUT            => 10,
    PDO::ATTR_PERSISTENT         => false,
];

// Enable SSL when connecting to Cloud Databases (e.g. TiDB, Aiven, PlanetScale, Railway)
if ($host !== '127.0.0.1' && $host !== 'localhost' && $host !== 'db') {
    $caPaths = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/ca-bundle.pem',
    ];
    foreach ($caPaths as $ca) {
        if (file_exists($ca)) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = $ca;
            break;
        }
    }
    if (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT')) {
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = false;
    }
}

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // If unknown database error (1049), attempt auto-creation of database
    if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
        try {
            $dsnNoDb = "mysql:host={$host};port={$port};charset={$charset}";
            $pdoInit = new PDO($dsnNoDb, $user, $pass, $options);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
            try { $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"); } catch (\Throwable $t) {}
        } catch (PDOException $ex) {
            error_log('Database creation failed: ' . $ex->getMessage());
            die('Database connection failed: ' . htmlspecialchars($ex->getMessage()));
        }
    } else {
        error_log('Database connection error: ' . $e->getMessage());
        if ($appDebug) {
            die('Database connection failed: ' . htmlspecialchars($e->getMessage()) . '<br><br>Host: ' . htmlspecialchars($host) . '<br>Port: ' . htmlspecialchars($port) . '<br>DB: ' . htmlspecialchars($db) . '<br>User: ' . htmlspecialchars($user));
        } else {
            die('Database connection error. Please verify configuration or check server logs. (Add ?debug=1 to URL or set APP_DEBUG=true to view exact error details)');
        }
    }
}

try { $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"); } catch (\Throwable $t) {}


// Auto-create sessions table if missing
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS sessions (
            id VARCHAR(128) NOT NULL PRIMARY KEY,
            data TEXT NOT NULL,
            last_access INT NOT NULL,
            INDEX idx_last_access (last_access)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    // Session table check fallback
}


