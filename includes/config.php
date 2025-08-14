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
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // change to 'development' if needed
define('APP_URL', getenv('APP_URL') ?: ''); // e.g. https://files.example.com

// FIXED: use consistent absolute storage path
define('STORAGE_PATH', __DIR__ . '/../storage');

//////////////////////
// Database credentials
//////////////////////
define('DB_HOST', getenv('DB_HOST') ?: 'sql113.infinityfree.com');
define('DB_NAME', getenv('DB_NAME') ?: 'if0_39687575_onlinestorage');
define('DB_USER', getenv('DB_USER') ?: 'if0_39687575');
define('DB_PASS', getenv('DB_PASS') ?: 'at14july1989');

//////////////////////
// Session cookie security
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
    session_set_cookie_params($cookieParams);
} else {
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
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

//////////////////////
// Include DB & helpers
//////////////////////
require_once __DIR__ . '/db.ph_
