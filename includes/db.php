<?php
// File: includes/db.php
// Robust DB connection that logs errors instead of dying unexpectedly.

$pdo = null;
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
} catch (Exception $e) {
    // Set a global message so config.php can present it
    $msg = 'DB connection error: ' . $e->getMessage();

    // Ensure storage path exists (best-effort)
    $logDir = __DIR__ . '/../storage';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    // Attempt to log the error to a file (best-effort)
    @file_put_contents($logDir . '/db_errors.log', date('c') . ' - ' . $msg . PHP_EOL, FILE_APPEND | LOCK_EX);

    // Also send to PHP error log
    error_log($msg);

    // Provide variable for config.php to read & display in dev mode
    $GLOBALS['DB_ERROR_MESSAGE'] = $msg;

    // Keep $pdo as null so config.php can handle the failure gracefully
    $pdo = null;
}
