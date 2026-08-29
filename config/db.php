<?php
// ============================================================================
// Database Connection (PDO + Prepared Statements)
// Supports .env files, environment variables, and local development fallbacks
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
            // Remove optional surrounding quotes
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

// Database settings with safe defaults for XAMPP / Laragon
$host    = $getEnvVar(['DB_HOST'], '127.0.0.1');
$port    = $getEnvVar(['DB_PORT'], '3306');
$db      = $getEnvVar(['DB_NAME', 'DB_DATABASE'], 'inventory_db');
$user    = $getEnvVar(['DB_USER', 'DB_USERNAME'], 'root');
$pass    = $getEnvVar(['DB_PASS', 'DB_PASSWORD'], '');
$charset = 'utf8mb4';

$appDebug = strtolower((string) $getEnvVar(['APP_DEBUG'], 'false')) === 'true';

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    // Log error internally without exposing credentials to the client
    error_log('Database connection error: ' . $e->getMessage());

    if ($appDebug) {
        die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
    } else {
        die('Database connection error. Please verify configuration or check server logs.');
    }
}
