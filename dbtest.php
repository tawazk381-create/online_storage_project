<?php
// dbtest.php - small script to test DB connection using your app's config
require __DIR__ . '/includes/config.php';
header('Content-Type: text/plain; charset=utf-8');

if (isset($pdo) && $pdo instanceof PDO) {
    echo "PDO connection OK\n";
    try {
        $stmt = $pdo->query("SELECT DATABASE() AS db");
        $row = $stmt->fetch();
        echo "Connected DB: " . ($row['db'] ?? 'unknown') . "\n";
    } catch (Exception $e) {
        echo "Query error: " . $e->getMessage() . "\n";
    }
} else {
    echo "PDO connection failed\n";
    $msg = $GLOBALS['DB_ERROR_MESSAGE'] ?? 'No DB error message set';
    echo $msg . "\n";

    $logFile = __DIR__ . '/storage/db_errors.log';
    if (file_exists($logFile)) {
        echo "\nContents of storage/db_errors.log:\n";
        echo file_get_contents($logFile);
    } else {
        echo "\nNo storage/db_errors.log found\n";
    }
}
