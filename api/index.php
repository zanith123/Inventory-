<?php
// ============================================================================
// Vercel Serverless Entry Router for PHP
// ============================================================================

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$uri = urldecode($uri);
$root = dirname(__DIR__);

$relPath = ltrim($uri, '/');
$target = $root . '/' . $relPath;

if ($relPath !== '' && file_exists($target)) {
    if (is_dir($target)) {
        $indexFile = rtrim($target, '/') . '/index.php';
        if (file_exists($indexFile)) {
            $_SERVER['SCRIPT_NAME'] = '/' . rtrim($relPath, '/') . '/index.php';
            $_SERVER['SCRIPT_FILENAME'] = $indexFile;
            chdir(dirname($indexFile));
            require $indexFile;
            exit;
        }
    } elseif (str_ends_with($target, '.php')) {
        $_SERVER['SCRIPT_NAME'] = '/' . $relPath;
        $_SERVER['SCRIPT_FILENAME'] = $target;
        chdir(dirname($target));
        require $target;
        exit;
    }
}

// Fallback to root index.php
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
chdir($root);
require $root . '/index.php';
