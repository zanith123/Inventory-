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
    try { $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"); } catch (\Throwable $t) {}
} catch (PDOException $e) {
    // If unknown database error (1049), attempt auto-creation of MySQL database
    if ($e->getCode() == 1049 || str_contains($e->getMessage(), 'Unknown database')) {
        try {
            $dsnNoDb = "mysql:host={$host};port={$port};charset={$charset}";
            $pdoInit = new PDO($dsnNoDb, $user, $pass, $options);
            $pdoInit->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo = new PDO($dsn, $user, $pass, $options);
            try { $pdo->exec("SET SESSION sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))"); } catch (\Throwable $t) {}
        } catch (PDOException $ex) {
            $pdo = null;
        }
    } else {
        $pdo = null;
    }

    // Fallback to SQLite if MySQL is unavailable (e.g. Vercel serverless without remote MySQL configured)
    if (!$pdo) {
        try {
            $sqlitePath = sys_get_temp_dir() . '/inventory_v2.sqlite';
            $pdo = new PDO('sqlite:' . $sqlitePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // Auto-seed SQLite schema & default accounts if missing
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS roles (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE);
                INSERT OR IGNORE INTO roles (id, name) VALUES (1, 'Admin'), (2, 'User'), (3, 'Viewer');
                
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password TEXT, role_id INTEGER DEFAULT 2, avatar TEXT, must_change_password INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                );
                
                CREATE TABLE IF NOT EXISTS categories (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, slug TEXT UNIQUE, note TEXT, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
                CREATE TABLE IF NOT EXISTS units (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, note TEXT);
                CREATE TABLE IF NOT EXISTS suppliers (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, phone TEXT, email TEXT, address TEXT, note TEXT);
                CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, sku TEXT UNIQUE, barcode TEXT, category_id INTEGER, supplier_id INTEGER, unit_id INTEGER, note TEXT, cost_price REAL DEFAULT 0, sale_price REAL DEFAULT 0, min_stock INTEGER DEFAULT 0, current_stock INTEGER DEFAULT 0, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
                CREATE TABLE IF NOT EXISTS stock_transactions (id INTEGER PRIMARY KEY AUTOINCREMENT, reference TEXT UNIQUE, type TEXT, transaction_date TEXT, note TEXT, supplier_id INTEGER, user_id INTEGER, created_at DATETIME DEFAULT CURRENT_TIMESTAMP);
                CREATE TABLE IF NOT EXISTS stock_transaction_items (id INTEGER PRIMARY KEY AUTOINCREMENT, transaction_id INTEGER, product_id INTEGER, qty INTEGER, unit_price REAL, subtotal REAL);
                CREATE TABLE IF NOT EXISTS sessions (id TEXT PRIMARY KEY, data TEXT, last_access INTEGER);
            ");

            // Seed default Admin & Staff if no users exist
            $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
            if ($userCount === 0) {
                $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
                $staffPass = password_hash('user123', PASSWORD_BCRYPT);
                $pdo->exec("INSERT INTO users (name, email, password, role_id) VALUES ('System Admin', 'admin@inventory.com', '{$adminPass}', 1)");
                $pdo->exec("INSERT INTO users (name, email, password, role_id) VALUES ('Inventory Staff', 'staff@inventory.com', '{$staffPass}', 2)");

                $pdo->exec("INSERT OR IGNORE INTO categories (name, slug) VALUES ('Laptops', 'laptops'), ('Smartphones', 'smartphones'), ('Accessories', 'accessories')");
                $pdo->exec("INSERT OR IGNORE INTO units (name) VALUES ('pcs'), ('box'), ('set')");
                $pdo->exec("INSERT OR IGNORE INTO suppliers (name, email) VALUES ('Tech Supplier Ltd', 'supplier@tech.com')");
                $pdo->exec("INSERT OR IGNORE INTO products (name, sku, barcode, category_id, supplier_id, unit_id, cost_price, sale_price, min_stock, current_stock) VALUES ('MacBook Pro M3', 'LAP-MAC-M3', 'LAP-MAC-M3', 1, 1, 1, 1200.00, 1499.00, 5, 12)");
                $pdo->exec("INSERT OR IGNORE INTO products (name, sku, barcode, category_id, supplier_id, unit_id, cost_price, sale_price, min_stock, current_stock) VALUES ('Dell XPS 15', 'LAP-DELL-XPS', 'LAP-DELL-XPS', 1, 1, 1, 950.00, 1199.00, 3, 2)");
            }
        } catch (\Throwable $sqEx) {
            error_log('Database connection error: ' . $e->getMessage());
            if ($appDebug) {
                die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
            } else {
                die('Database connection error. Please verify configuration or check server logs.');
            }
        }
    }
}

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
} catch (\Throwable $e) {
    // Session table check fallback
}
