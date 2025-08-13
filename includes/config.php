<?php
// File: includes/config.php
// Loads environment (from real env or optional .env file) and defines constants.
// IMPORTANT: Place a production .env outside the web root or set real environment variables.

declare(strict_types=1);

require_once __DIR__ . '/session.php'; // start secure session ASAP

// Load .env if present (very small loader - only used if env vars not already set)
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        [$key, $val] = array_map('trim', explode('=', $line, 2) + [null, null]);
        if ($key !== null && getenv($key) === false) {
            putenv("$key=$val");
            $_ENV[$key] = $val;
        }
    }
}

// Fetch configuration from environment
define('DB_HOST', getenv('DB_HOST') ?: 'sql113.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_39687575_onlinestorage');
define('DB_USER', getenv('DB_USER') ?: 'if0_39687575');
define('DB_PASS', getenv('DB_PASS') ?: 'at14july1989'); // ensure to set in production via env

// Storage root (must be outside public webroot if possible)
define('STORAGE_ROOT', getenv('STORAGE_ROOT') ?: dirname(__DIR__) . '/storage');

// Default upload restrictions
define('MAX_UPLOAD_BYTES', intval(getenv('MAX_UPLOAD_BYTES') ?: 50 * 1024 * 1024)); // 50MB

// Whether running in production (affects error display)
define('APP_ENV', getenv('APP_ENV') ?: 'development');

// Error settings
if (APP_ENV === 'production') {
    error_reporting(E_ALL);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
