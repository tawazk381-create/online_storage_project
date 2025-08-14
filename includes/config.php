<?php
// File: includes/config.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'sql113.infinityfree.com');
define('DB_NAME', 'if0_39687575_onlinestorage');
define('DB_USER', 'if0_39687575');
define('DB_PASS', 'at14july1989');


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
?>
