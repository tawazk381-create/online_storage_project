<?php
// File: includes/config.php
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('DB_HOST', 'localhost');
define('DB_NAME', 'onlinestorage');
define('DB_USER', 'root');
define('DB_PASS', '');


require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
?>
