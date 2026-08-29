<?php
// ============================================================================
// Automated Database Setup & Installer Script
// Runs schema.sql and seed.sql automatically
// Usage via CLI: php setup.php
// Usage via Web: Open http://your-domain/setup.php in browser
// ============================================================================

require_once __DIR__ . '/config/db.php';

$isCli = (php_sapi_name() === 'cli');

function outputMsg(string $msg, string $type = 'info'): void {
    global $isCli;
    if ($isCli) {
        $prefix = match($type) {
            'success' => '[OK] ',
            'error'   => '[ERROR] ',
            'warning' => '[WARN] ',
            default   => '[INFO] ',
        };
        echo $prefix . strip_tags($msg) . PHP_EOL;
    } else {
        $color = match($type) {
            'success' => '#198754',
            'error'   => '#dc3545',
            'warning' => '#ffc107',
            default   => '#0d6efd',
        };
        echo "<div style='padding: 10px 15px; margin-bottom: 8px; border-radius: 6px; background: {$color}22; border-left: 4px solid {$color}; color: #212529; font-family: system-ui, sans-serif;'>{$msg}</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Setup — Inventory Management System</title></head>";
    echo "<body style='max-width: 700px; margin: 40px auto; padding: 20px; font-family: system-ui, -apple-system, sans-serif; background: #f8f9fa; color: #212529;'>";
    echo "<h2 style='margin-top:0;'>📦 Inventory System Database Setup</h2>";
}

outputMsg("Connecting to database server...");

try {
    // 1. Run Schema
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new Exception("schema.sql file not found at: {$schemaFile}");
    }

    outputMsg("Reading database schema from schema.sql...");
    $sqlSchema = file_get_contents($schemaFile);

    // Split queries safely
    $queries = array_filter(array_map('trim', explode(';', $sqlSchema)));

    foreach ($queries as $q) {
        if ($q === '' || str_starts_with($q, '--') || str_starts_with($q, '/*')) {
            continue;
        }
        $pdo->exec($q);
    }
    outputMsg("Database tables created successfully!", "success");

    // 2. Run Seed
    $seedFile = __DIR__ . '/database/seed.sql';
    if (file_exists($seedFile)) {
        outputMsg("Reading seed data from seed.sql...");
        $sqlSeed = file_get_contents($seedFile);
        $seedQueries = array_filter(array_map('trim', explode(';', $sqlSeed)));

        foreach ($seedQueries as $sq) {
            if ($sq === '' || str_starts_with($sq, '--') || str_starts_with($sq, '/*')) {
                continue;
            }
            try {
                $pdo->exec($sq);
            } catch (PDOException $se) {
                // Ignore duplicate key errors if already seeded
                if ($se->getCode() !== '23000') {
                    throw $se;
                }
            }
        }
        outputMsg("Demo datasets and admin user seeded successfully!", "success");
    }

    outputMsg("Database installation and migration complete!", "success");
    if (!$isCli) {
        $baseUrl = defined('BASE_URL') ? BASE_URL : '';
        echo "<hr style='margin: 20px 0; border: none; border-top: 1px solid #dee2e6;'>";
        echo "<p style='font-size: 1.1rem;'><strong>Default Login Credentials:</strong></p>";
        echo "<ul>";
        echo "<li><strong>Admin:</strong> admin@inventory.com / <code>admin123</code></li>";
        echo "<li><strong>Staff:</strong> staff@inventory.com / <code>user123</code></li>";
        echo "</ul>";
        echo "<p><a href='{$baseUrl}/auth/login.php' style='display: inline-block; padding: 10px 20px; background: #0d6efd; color: white; text-decoration: none; border-radius: 6px; font-weight: 600;'>Go to Login Page &rarr;</a></p>";
    }
} catch (Exception $e) {
    outputMsg("Setup failed: " . htmlspecialchars($e->getMessage()), "error");
}

if (!$isCli) {
    echo "</body></html>";
}
