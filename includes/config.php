<?php
// File: includes/config.php
// Production-friendly config. Replace .env values as needed.

//////////////////////
// Optional .env loader
//////////////////////
$dotenvPath = __DIR__ . '/../.env';
if (file_exists($dotenvPath)) {
    $lines = file($dotenvPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $k = trim($parts[0]);
            $v = trim($parts[1]);
            if (getenv($k) === false) putenv("$k=$v");
        }
    }
}

//////////////////////
// App / env config
//////////////////////
// If you want to enable verbose errors on the live server temporarily, set APP_ENV=development in .env
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'production' or 'development'
define('APP_URL', getenv('APP_URL') ?: ''); // e.g. https://files.example.com

// Use an absolute path for storage. Ensure this folder exists and is writable by PHP.
define('STORAGE_PATH', __DIR__ . '/../storage');

//////////////////////
// Database credentials
// Prefer putting these in .env in production
//////////////////////
define('DB_HOST', getenv('DB_HOST') ?: 'sql107.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_40809906_onlinestorage');
define('DB_USER', getenv('DB_USER') ?: 'if0_40809906');
define('DB_PASS', getenv('DB_PASS') ?: 'CtV2hiDrDOI');

//////////////////////
// Session cookie security (must call before session_start)
//////////////////////
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

$cookieParams = [
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax'
];

if (PHP_VERSION_ID >= 70300) {
    // PHP 7.3+ supports array form including samesite
    session_set_cookie_params($cookieParams);
} else {
    // Fallback for older PHP versions; this attempts to include samesite as part of path
    session_set_cookie_params(
        $cookieParams['lifetime'],
        $cookieParams['path'] . '; samesite=' . $cookieParams['samesite'],
        $cookieParams['domain'],
        $cookieParams['secure'],
        $cookieParams['httponly']
    );
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

//////////////////////
// Error display policy
//////////////////////
if (strtolower(APP_ENV) === 'production') {
    ini_set('display_errors', 0);
    error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
} else {
    // development mode
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

//////////////////////
// Include DB & helpers
// Note: DB errors are handled in includes/db.php and will be surfaced below.
//////////////////////
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

// If db.php set $pdo = null (failed to connect), show friendly message (detailed in dev mode)
if (!isset($pdo) || $pdo === null) {
    $dbErr = $GLOBALS['DB_ERROR_MESSAGE'] ?? 'Unknown database error';

    // Attempt to ensure storage exists for logging
    if (!is_dir(STORAGE_PATH)) {
        @mkdir(STORAGE_PATH, 0755, true);
    }

    // Write a short marker so you can inspect the file on the server
    @file_put_contents(STORAGE_PATH . '/bootstrap_errors.log', date('c') . " - DB ERROR - " . $dbErr . PHP_EOL, FILE_APPEND | LOCK_EX);

    if (strtolower(APP_ENV) !== 'production') {
        // Show helpful diagnostic to the browser (development)
        header('Content-Type: text/html; charset=utf-8', true, 500);
        echo "<h1>Database connection failure</h1>";
        echo "<p>The application could not connect to the database. Check your DB credentials and network access.</p>";
        echo "<pre>" . htmlspecialchars($dbErr, ENT_QUOTES, 'UTF-8') . "</pre>";
        echo "<p>Also logged to <code>storage/bootstrap_errors.log</code></p>";
    } else {
        // Production: minimal message, but with 500 status
        header($_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error', true, 500);
        echo 'Server configuration error. Please contact the site administrator.';
    }
    exit;
}
