<?php
// ============================================================================
// Base URL and Asset Configuration Helper
// Automatically calculates URL prefix for Apache, Nginx, Docker, CLI, Subfolders
// ============================================================================

if (!defined('BASE_URL')) {
    $envUrl = getenv('APP_URL') ?: ($_ENV['APP_URL'] ?? null);

    if (!empty($envUrl)) {
        $parsed = parse_url($envUrl, PHP_URL_PATH);
        define('BASE_URL', rtrim($parsed ?: '', '/'));
    } elseif (!empty($_SERVER['DOCUMENT_ROOT'])) {
        $docRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/');
        $appRoot = rtrim(str_replace('\\', '/', dirname(__DIR__)), '/');

        if ($docRoot !== '' && str_starts_with($appRoot, $docRoot)) {
            define('BASE_URL', str_replace($docRoot, '', $appRoot));
        } else {
            define('BASE_URL', '');
        }
    } else {
        define('BASE_URL', '');
    }
}

// Cache-busting version for assets/style.css
if (!defined('ASSET_VER')) {
    $cssFile = dirname(__DIR__) . '/assets/style.css';
    define('ASSET_VER', file_exists($cssFile) ? (string) filemtime($cssFile) : '1.0');
}
