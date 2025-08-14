<?php
// upload_debug.php — temporary debug page for upload failures
// Put this in your web root and open it in a browser. Remove it when done.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Upload Debug</h2>";
echo "<p><strong>DO NOT leave this file on a production server long-term.</strong></p>";

// Try to load app config if available (silently continue if missing)
$configLoaded = false;
if (file_exists(__DIR__ . '/includes/config.php')) {
    require_once __DIR__ . '/includes/config.php';
    $configLoaded = true;
}

// Show basic environment info
echo "<h3>Environment</h3><pre>";
echo "PHP version: " . phpversion() . "\n";
echo "Server software: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'n/a') . "\n";
echo "</pre>";

// Show important PHP ini values for uploads
$iniKeys = [
    'upload_max_filesize','post_max_size','memory_limit',
    'max_execution_time','max_input_time','file_uploads',
    'upload_tmp_dir','open_basedir'
];
echo "<h3>PHP ini values</h3><pre>";
foreach ($iniKeys as $k) {
    echo str_pad($k,22) . ': ' . ini_get($k) . "\n";
}
echo "</pre>";

// Show STORAGE_PATH if config loaded
echo "<h3>STORAGE_PATH & paths</h3><pre>";
if ($configLoaded && defined('STORAGE_PATH')) {
    echo "STORAGE_PATH: " . STORAGE_PATH . "\n";
    echo "STORAGE_PATH exists: " . (is_dir(STORAGE_PATH) ? 'YES' : 'NO') . "\n";
    echo "STORAGE_PATH writable: " . (is_writable(STORAGE_PATH) ? 'YES' : 'NO') . "\n";
    $free = @disk_free_space(STORAGE_PATH);
    echo "disk_free_space (bytes): " . ($free === false ? 'n/a' : $free) . "\n";
    // show where upload_errors.log would be
    echo "upload_errors.log: " . STORAGE_PATH . "/upload_errors.log\n";
} else {
    echo "STORAGE_PATH not defined (includes/config.php not loaded or STORAGE_PATH missing).\n";
    echo "Default project storage: " . __DIR__ . "/storage\n";
    echo "Default storage exists: " . (is_dir(__DIR__ . '/storage') ? 'YES' : 'NO') . "\n";
}
echo "</pre>";

// Show tmp dir files (if accessible) — do not list too many
echo "<h3>Temporary upload dir sample</h3><pre>";
$tmp = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
echo "upload_tmp_dir: $tmp\n";
if (is_dir($tmp)) {
    $files = array_slice(scandir($tmp), 0, 10);
    echo "sample files in tmp:\n";
    foreach ($files as $f) {
        if ($f === '.' || $f === '..') continue;
        echo " - $f\n";
    }
} else {
    echo "tmp dir not accessible\n";
}
echo "</pre>";

// Show a simple test upload form
echo '<h3>Test upload form</h3>';
echo '<form method="post" enctype="multipart/form-data">';
echo '<input type="file" name="testfile" required> ';
echo '<button type="submit">Upload test file</button>';
echo '</form>';

// If a POST happened, show debug info
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h3>Upload POST diagnostics</h3><pre>";
    // print $_FILES
    echo "\$_FILES:\n";
    ob_start();
    print_r($_FILES);
    $fout = ob_get_clean();
    echo htmlspecialchars($fout) . "\n";

    if (!isset($_FILES['testfile'])) {
        echo "No file arrived in \$_FILES['testfile']\n";
        echo "</pre>";
        exit;
    }

    $file = $_FILES['testfile'];
    // show upload error codes
    $err = $file['error'];
    echo "upload error code: $err\n";
    $errors = [
        UPLOAD_ERR_OK => 'UPLOAD_ERR_OK',
        UPLOAD_ERR_INI_SIZE => 'UPLOAD_ERR_INI_SIZE',
        UPLOAD_ERR_FORM_SIZE => 'UPLOAD_ERR_FORM_SIZE',
        UPLOAD_ERR_PARTIAL => 'UPLOAD_ERR_PARTIAL',
        UPLOAD_ERR_NO_FILE => 'UPLOAD_ERR_NO_FILE',
        UPLOAD_ERR_NO_TMP_DIR => 'UPLOAD_ERR_NO_TMP_DIR',
        UPLOAD_ERR_CANT_WRITE => 'UPLOAD_ERR_CANT_WRITE',
        UPLOAD_ERR_EXTENSION => 'UPLOAD_ERR_EXTENSION',
    ];
    echo "upload error name: " . ($errors[$err] ?? 'unknown') . "\n";

    // show tmp_name and is_uploaded_file check
    echo "tmp_name: " . ($file['tmp_name'] ?? '') . "\n";
    echo "is_uploaded_file(tmp_name): " . (isset($file['tmp_name']) && is_uploaded_file($file['tmp_name']) ? 'YES' : 'NO') . "\n";

    // Attempt to move to a test location
    $destRoot = (defined('STORAGE_PATH') ? STORAGE_PATH : __DIR__ . '/storage');
    $destDir = $destRoot . '/debug_uploads';
    echo "destRoot: $destRoot\n";
    echo "destDir: $destDir\n";

    if (!is_dir($destRoot)) {
        echo "destRoot does not exist. Attempting to create...\n";
        @mkdir($destRoot, 0755, true);
        echo "mkdir destRoot result: " . (is_dir($destRoot) ? 'OK' : 'FAILED') . "\n";
    }
    if (!is_dir($destDir)) {
        echo "destDir does not exist. Attempting to create...\n";
        @mkdir($destDir, 0755, true);
        echo "mkdir destDir result: " . (is_dir($destDir) ? 'OK' : 'FAILED') . "\n";
    }

    echo "destDir writable: " . (is_writable($destDir) ? 'YES' : 'NO') . "\n";

    $origName = basename($file['name']);
    $safe = preg_replace('/[^A-Za-z0-9_\-\.]/', '_', $origName);
    $target = $destDir . '/' . $safe;
    echo "Attempting to move uploaded file to: $target\n";

    $moved = false;
    if (isset($file['tmp_name']) && $file['error'] === UPLOAD_ERR_OK) {
        $moved = @move_uploaded_file($file['tmp_name'], $target);
    } else {
        echo "Not attempting move_uploaded_file due to earlier error or missing tmp_name.\n";
    }

    echo "move_uploaded_file result: " . ($moved ? 'OK' : 'FAILED') . "\n";
    if (!$moved) {
        echo "error_get_last():\n";
        $el = error_get_last();
        echo htmlspecialchars(print_r($el, true)) . "\n";
    } else {
        echo "File moved; target filesize: " . filesize($target) . "\n";
    }

    // Write a short log to STORAGE_PATH if possible
    if (is_dir($destRoot) && is_writable($destRoot)) {
        $logLine = date('c') . " - upload_debug - user_agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? '') . " - original: $origName - moved: " . ($moved ? 'YES' : 'NO') . "\n";
        @file_put_contents($destRoot . '/upload_debug.log', $logLine, FILE_APPEND | LOCK_EX);
        echo "Wrote upload_debug.log in destRoot.\n";
    } else {
        echo "Could not write upload_debug.log (destRoot missing or not writable).\n";
    }

    echo "</pre>";
}

echo "<p>When done, delete this file from the server.</p>";
